<?php

namespace App\Http\Controllers\Showcase;

use App\Enums\EducationLevel;
use App\Enums\JobPostingType;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Showcase\OpenJobPostings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Common job board: open offers of every organisation listed on it (§4.1).
 */
class JobBoardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(JobPostingType::class)],
            'organisation' => ['nullable', 'string', 'max:60'],
            'lieu' => ['nullable', 'string', 'max:100'],
            'niveau' => ['nullable', Rule::enum(EducationLevel::class)],
            'cloture' => ['nullable', Rule::in(['semaine', 'mois'])],
            'tri' => ['nullable', Rule::in(['recentes', 'cloture'])],
        ])->valid();

        $query = OpenJobPostings::query()
            ->whereHas('organization', fn (Builder $q) => $q->where('listed_on_job_board', true))
            ->with('organization:id,name,slug,logo_path');

        $search = trim((string) ($filters['q'] ?? ''));
        if (mb_strlen($search) >= 3) {
            $query->whereFullText(['title', 'search_text'], $search);
        } elseif ($search !== '') {
            $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%');
        }

        $query
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['niveau'] ?? null, fn (Builder $q, string $level) => $q->where('education_level', $level))
            ->when($filters['lieu'] ?? null, fn (Builder $q, string $place) => $q->where('location', 'like', '%'.addcslashes($place, '%_\\').'%'))
            ->when($filters['organisation'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('organization', fn (Builder $o) => $o->where('slug', $slug)))
            ->when(($filters['cloture'] ?? null) === 'semaine', fn (Builder $q) => $q->where('closes_at', '<=', now()->addWeek()))
            ->when(($filters['cloture'] ?? null) === 'mois', fn (Builder $q) => $q->where('closes_at', '<=', now()->addMonth()));

        ($filters['tri'] ?? null) === 'cloture'
            ? $query->orderBy('closes_at')
            : $query->orderByDesc('published_at');

        return view('showcase.job-board', [
            'postings' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'organizations' => Cache::remember('showcase.board-organizations', 300, fn () => Organization::query()
                ->where('listed_on_job_board', true)
                ->whereHas('jobPostings', fn (Builder $q) => $q->whereIn('id', OpenJobPostings::query()->select('id')))
                ->orderBy('name')
                ->pluck('name', 'slug')
                ->all()),
        ]);
    }
}
