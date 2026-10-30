<?php

namespace App\Actions\Candidates;

use App\Models\CandidateEducation;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class SaveCandidateEducation
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $candidate, ?CandidateEducation $education, array $data): CandidateEducation
    {
        abort_unless($candidate->isCandidate(), 403);
        abort_if($education !== null && $education->user_id !== $candidate->getKey(), 404);

        $year = (int) now()->format('Y');
        $data = Validator::make($data, [
            'degree' => ['required', 'string', 'max:255'],
            'institution' => ['required', 'string', 'max:255'],
            'field' => ['nullable', 'string', 'max:255'],
            'start_year' => ['nullable', 'integer', 'min:1950', 'max:'.$year],
            'end_year' => ['nullable', 'integer', 'min:1950', 'max:'.($year + 8), 'gte:start_year'],
        ])->validate();

        $education ??= new CandidateEducation;
        $education->fill($data);
        $education->user_id = $candidate->getKey();
        $education->save();

        return $education;
    }
}
