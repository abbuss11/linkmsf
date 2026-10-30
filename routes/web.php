<?php

use App\Http\Controllers\Candidate\ApplicationController;
use App\Http\Controllers\Candidate\AuthenticatedSessionController;
use App\Http\Controllers\Candidate\DashboardController;
use App\Http\Controllers\Candidate\DocumentDownloadController;
use App\Http\Controllers\Candidate\EmailVerificationController;
use App\Http\Controllers\Candidate\NewPasswordController;
use App\Http\Controllers\Candidate\PasswordResetLinkController;
use App\Http\Controllers\Candidate\ProfileController;
use App\Http\Controllers\Candidate\ProfileDocumentController;
use App\Http\Controllers\Candidate\RegisteredCandidateController;
use App\Http\Controllers\Candidate\UploadController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OrganizationRequestController;
use App\Http\Controllers\Showcase\CareerPageController;
use App\Http\Controllers\Showcase\JobBoardController;
use App\Http\Controllers\Showcase\JobPostingController;
use App\Http\Controllers\Showcase\SitemapController;
use App\Http\Middleware\EnsureUserIsCandidate;
use App\Http\Middleware\ProtectAgainstSpam;
use App\Http\Middleware\PublicPageCache;
use App\Livewire\ApplicationWizard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

// Showcase (§4.1): common job board, offer pages, career pages
Route::middleware(PublicPageCache::class)->group(function () {
    Route::get('/offres', JobBoardController::class)->name('job-postings.index');
    Route::get('/offres/{slug}', JobPostingController::class)->name('job-postings.show');
    Route::get('/o/{slug}', CareerPageController::class)->name('career-pages.show');
    Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
});

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

        Route::middleware('verified')->group(function () {
            Route::get('/espace-candidat', DashboardController::class)->name('candidate.dashboard');

            // Applications (§4.4). {application} only resolves among the
            // candidate's own applications (see AppServiceProvider).
            Route::get('/offres/{slug}/postuler', [ApplicationController::class, 'start'])->name('job-postings.apply');
            Route::livewire('/candidatures/{application}/modifier', ApplicationWizard::class)->name('candidate.applications.edit');
            Route::get('/candidatures/{application}', [ApplicationController::class, 'show'])->name('candidate.applications.show');
            Route::get('/candidatures/{application}/confirmation', [ApplicationController::class, 'confirmation'])->name('candidate.applications.confirmation');
            Route::post('/candidatures/{application}/retrait', [ApplicationController::class, 'withdraw'])->name('candidate.applications.withdraw');

            Route::middleware('throttle:uploads')->group(function () {
                Route::post('/candidatures/{application}/documents', [UploadController::class, 'init'])->name('candidate.applications.documents.init');
                Route::put('/candidatures/{application}/documents/{document}', [UploadController::class, 'chunk'])->name('candidate.applications.documents.chunk');
            });

            // Global profile and reusable documents (§4.2)
            Route::get('/profil', [ProfileController::class, 'edit'])->name('candidate.profile');
            Route::put('/profil', [ProfileController::class, 'update'])->name('candidate.profile.update');
            Route::post('/profil/formations', [ProfileController::class, 'storeEducation'])->name('candidate.educations.store');
            Route::delete('/profil/formations/{education}', [ProfileController::class, 'destroyEducation'])->whereNumber('education')->name('candidate.educations.destroy');
            Route::post('/profil/experiences', [ProfileController::class, 'storeExperience'])->name('candidate.experiences.store');
            Route::delete('/profil/experiences/{experience}', [ProfileController::class, 'destroyExperience'])->whereNumber('experience')->name('candidate.experiences.destroy');

            Route::get('/documents', [ProfileDocumentController::class, 'index'])->name('candidate.documents');
            Route::post('/documents', [ProfileDocumentController::class, 'store'])->middleware('throttle:uploads')->name('candidate.documents.store');
            Route::delete('/documents/{document}', [ProfileDocumentController::class, 'destroy'])->name('candidate.documents.destroy');
            Route::post('/documents/{document}/cv-par-defaut', [ProfileDocumentController::class, 'makeDefault'])->name('candidate.documents.default');
        });
    });

    // Short-lived signed links; the policy is checked again on download.
    Route::middleware('signed')->group(function () {
        Route::get('/fichiers/candidature/{document}', [DocumentDownloadController::class, 'application'])->name('documents.application');
        Route::get('/fichiers/profil/{document}', [DocumentDownloadController::class, 'profile'])->name('documents.profile');
    });
});
