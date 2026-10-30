<?php

namespace App\Http\Controllers;

use App\Actions\Members\AcceptInvitation;
use App\Exceptions\ActionFailed;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Public invitation link. The invitation is looked up across organisations
 * on purpose: its secret token, not a session, is what grants access.
 */
class InvitationController extends Controller
{
    public function show(Request $request, string $invitation): View
    {
        $invitation = $this->find($invitation);
        $token = (string) $request->query('token');
        $organization = $invitation ? Organization::query()->find($invitation->organization_id) : null;

        $state = $this->state($invitation, $organization, $token, $request->user());

        if ($state === 'login_required') {
            // Filament's login redirects back here afterwards.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('invitations.show', [
            'state' => $state,
            'invitation' => $invitation,
            'organization' => $organization,
            'token' => $token,
            'loginUrl' => Filament::getPanel('app')->getLoginUrl(),
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $invitation, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = $this->find($invitation);
        abort_if($invitation === null, 404);

        /** @var User|null $currentUser */
        $currentUser = $request->user();

        try {
            $user = $acceptInvitation->handle(
                $invitation,
                (string) $request->validated('token'),
                $currentUser,
                $currentUser === null ? [
                    'name' => (string) $request->validated('name'),
                    'password' => (string) $request->validated('password'),
                ] : null,
            );
        } catch (ActionFailed $exception) {
            return back()->withErrors(['invitation' => $exception->getMessage()]);
        }

        if ($currentUser === null) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        $organization = Organization::query()->findOrFail($invitation->organization_id);

        return redirect()->to(Filament::getPanel('app')->getUrl($organization));
    }

    private function find(string $ulid): ?Invitation
    {
        return Invitation::queryAcrossOrganizations()->where('ulid', $ulid)->first();
    }

    private function state(?Invitation $invitation, ?Organization $organization, string $token, ?User $user): string
    {
        if ($invitation === null || $organization === null
            || ! $invitation->matchesToken($token) || ! $invitation->isPending() || ! $organization->isActive()) {
            return 'invalid';
        }

        if ($user !== null) {
            return match (true) {
                $user->isCandidate() => 'candidate_account',
                strcasecmp($user->email, $invitation->email) !== 0 => 'wrong_account',
                default => 'confirm',
            };
        }

        $existing = User::query()->where('email', $invitation->email)->first();

        return match (true) {
            $existing === null => 'register',
            $existing->isCandidate() => 'candidate_account',
            default => 'login_required',
        };
    }
}
