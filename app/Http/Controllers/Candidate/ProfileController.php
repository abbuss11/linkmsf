<?php

namespace App\Http\Controllers\Candidate;

use App\Actions\Candidates\SaveCandidateEducation;
use App\Actions\Candidates\SaveCandidateExperience;
use App\Actions\Candidates\UpdateCandidateProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global profile, education and experience (§4.2). Each record is looked up
 * through the candidate's own relations: someone else's id gives a 404.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $candidate = $this->candidate($request);

        return view('candidate.profile.edit', [
            'user' => $candidate,
            'profile' => $candidate->profile(),
            'educations' => $candidate->educations()->get(),
            'experiences' => $candidate->experiences()->get(),
        ]);
    }

    public function update(Request $request, UpdateCandidateProfile $updateProfile): RedirectResponse
    {
        $updateProfile->handle($this->candidate($request), $request->except(['_token', '_method']));

        return redirect()->route('candidate.profile')->with('status', __('candidate.profile.saved'));
    }

    public function storeEducation(Request $request, SaveCandidateEducation $save): RedirectResponse
    {
        $save->handle($this->candidate($request), null, $request->only(['degree', 'institution', 'field', 'start_year', 'end_year']));

        return redirect()->to(route('candidate.profile').'#formations')->with('status', __('candidate.profile.saved'));
    }

    public function destroyEducation(Request $request, int $education): RedirectResponse
    {
        $this->candidate($request)->educations()->findOrFail($education)->delete();

        return redirect()->to(route('candidate.profile').'#formations')->with('status', __('candidate.profile.deleted'));
    }

    public function storeExperience(Request $request, SaveCandidateExperience $save): RedirectResponse
    {
        $save->handle($this->candidate($request), null, $request->only(['title', 'employer', 'location', 'start_date', 'end_date', 'description']) + [
            'is_current' => $request->boolean('is_current'),
        ]);

        return redirect()->to(route('candidate.profile').'#experiences')->with('status', __('candidate.profile.saved'));
    }

    public function destroyExperience(Request $request, int $experience): RedirectResponse
    {
        $this->candidate($request)->experiences()->findOrFail($experience)->delete();

        return redirect()->to(route('candidate.profile').'#experiences')->with('status', __('candidate.profile.deleted'));
    }

    private function candidate(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isCandidate(), 403);

        return $user;
    }
}
