<?php

use App\Http\Controllers\Candidate\AuthenticatedSessionController;
use App\Http\Controllers\Candidate\DashboardController;
use App\Http\Controllers\Candidate\EmailVerificationController;
use App\Http\Controllers\Candidate\NewPasswordController;
use App\Http\Controllers\Candidate\PasswordResetLinkController;
use App\Http\Controllers\Candidate\RegisteredCandidateController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OrganizationRequestController;
use App\Http\Middleware\EnsureUserIsCandidate;
use App\Http\Middleware\ProtectAgainstSpam;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

// Organisation space request (public, validated by a superadmin)
Route::get('/demander-un-espace', [OrganizationRequestController::class, 'create'])->name('organization-requests.create');
Route::post('/demander-un-espace', [OrganizationRequestController::class, 'store'])
    ->middleware(['throttle:organization-requests', ProtectAgainstSpam::class])
    ->name('organization-requests.store');
Route::get('/demander-un-espace/merci', [OrganizationRequestController::class, 'thanks'])->name('organization-requests.thanks');

// Invitations to join an organisation
Route::get('/invitations/{invitation}', [InvitationController::class, 'show'])
    ->middleware(['signed', 'throttle:invitations'])
    ->name('invitations.show');
Route::post('/invitations/{invitation}', [InvitationController::class, 'accept'])
    ->middleware('throttle:invitations')
    ->name('invitations.accept');

// Candidate authentication
Route::middleware('guest')->group(function () {
    Route::get('/inscription', [RegisteredCandidateController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisteredCandidateController::class, 'store'])
        ->middleware(['throttle:register', ProtectAgainstSpam::class]);

    Route::get('/connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('/reinitialiser-mot-de-passe/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reinitialiser-mot-de-passe', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware(EnsureUserIsCandidate::class)->group(function () {
        Route::get('/email/verification', [EmailVerificationController::class, 'notice'])->name('verification.notice');
        Route::get('/email/verification/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
        Route::post('/email/verification/renvoyer', [EmailVerificationController::class, 'send'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        Route::get('/espace-candidat', DashboardController::class)
            ->middleware('verified')
            ->name('candidate.dashboard');
    });
});
