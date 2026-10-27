<?php

namespace App\Http\Controllers\Candidate;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('candidate.auth.forgot-password');
    }

    /**
     * Same answer whether or not the account exists, to avoid revealing emails.
     * Organisation members reset their password from /app.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $email = mb_strtolower(trim((string) $request->input('email')));

        if (User::query()->where('email', $email)->where('type', UserType::Candidate->value)->exists()) {
            Password::broker()->sendResetLink(['email' => $email]);
        }

        return back()->with('status', __('candidate.forgot.sent'));
    }
}
