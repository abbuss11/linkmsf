<?php

namespace App\Http\Controllers\Candidate;

use App\Actions\Candidates\RegisterCandidate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredCandidateController extends Controller
{
    public function create(): View
    {
        return view('candidate.auth.register');
    }

    public function store(RegisterRequest $request, RegisterCandidate $registerCandidate): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string} $data */
        $data = $request->safe()->only(['name', 'email', 'password']);

        $user = $registerCandidate->handle($data);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
