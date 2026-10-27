<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('candidate.auth.login');
    }

    /**
     * Only candidate accounts log in here; organisation members use /app/login.
     * The error message is the same either way, so it reveals nothing.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $authenticated = Auth::attemptWhen(
            $request->only('email', 'password'),
            fn (User $user): bool => $user->isCandidate(),
            $request->boolean('remember'),
        );

        if (! $authenticated) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('candidate.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
