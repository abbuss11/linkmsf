<?php

namespace App\Http\Controllers\Candidate;

use App\Actions\Applications\StartApplication;
use App\Actions\Applications\WithdrawApplication;
use App\Enums\JobPostingStatus;
use App\Exceptions\ActionFailed;
use App\Forms\Dynamic\FormSchema;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\JobPosting;
use App\Models\Organization;
use App\Models\User;
use App\Support\Applications\AnswerStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    /**
     * "Postuler" from an offer page: creates or resumes the draft.
     */
    public function start(Request $request, string $slug, StartApplication $startApplication): RedirectResponse
    {
        $posting = JobPosting::queryAcrossOrganizations()
            ->where('slug', $slug)
            ->where('status', JobPostingStatus::Published->value)
            ->firstOrFail();

        $source = $request->query('source') ?? $request->session()->get('application_source.'.$posting->getKey());

        try {
            $application = $startApplication->handle($this->candidate($request), $posting, is_string($source) ? $source : null);
        } catch (ActionFailed $exception) {
            $existing = Application::forCandidate($this->candidate($request))->where('job_posting_id', $posting->getKey())->first();

            return $existing !== null
                ? redirect()->route('candidate.applications.show', $existing->ulid)->with('status', $exception->getMessage())
                : redirect()->route('job-postings.show', $slug)->with('status', $exception->getMessage());
        }

        return redirect()->route('candidate.applications.edit', $application->ulid);
    }

    public function show(Request $request, Application $application): View
    {
        Gate::authorize('view', $application);

        $application->loadMissing(['jobPosting', 'formVersion']);

        return view('candidate.applications.show', [
            'application' => $application,
            'organization' => Organization::query()->findOrFail($application->organization_id),
            'schema' => FormSchema::fromArray($application->formVersion?->schema),
            'answers' => AnswerStore::load($application),
            'documents' => ApplicationDocument::queryAcrossOrganizations()
                ->where('application_id', $application->getKey())
                ->where('status', ApplicationDocument::COMPLETE)
                ->get(),
            'canEdit' => Gate::allows('update', $application),
            'canWithdraw' => Gate::allows('withdraw', $application),
        ]);
    }

    public function confirmation(Request $request, Application $application): View
    {
        Gate::authorize('view', $application);
        abort_unless($application->isSubmitted(), 404);

        return view('candidate.applications.confirmation', [
            'application' => $application->loadMissing('jobPosting'),
            'organization' => Organization::query()->findOrFail($application->organization_id),
        ]);
    }

    public function withdraw(Request $request, Application $application, WithdrawApplication $withdrawApplication): RedirectResponse
    {
        $withdrawApplication->handle($this->candidate($request), $application);

        return redirect()->route('candidate.applications.show', $application->ulid)
            ->with('status', __('applications.withdrawn'));
    }

    private function candidate(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
