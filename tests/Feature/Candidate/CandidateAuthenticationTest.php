<?php

use App\Enums\OrganizationRole;
use App\Enums\UserType;
use App\Http\Middleware\ProtectAgainstSpam;
use App\Models\User;
use App\Notifications\Auth\ResetCandidatePassword;
use App\Notifications\Auth\VerifyCandidateEmail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => Notification::fake());

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Zeinabou Harouna',
        'email' => 'Zeinabou@Example.test',
        'password' => 'motdepasse2026',
        'password_confirmation' => 'motdepasse2026',
        ProtectAgainstSpam::TRAP_FIELD => '',
        ProtectAgainstSpam::TIME_FIELD => Crypt::encryptString((string) now()->subSeconds(30)->getTimestamp()),
    ], $overrides);
}

it('registers a global candidate account and sends the verification email', function () {
    $this->post('/inscription', registrationPayload())->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'zeinabou@example.test')->sole();

    expect($user->type)->toBe(UserType::Candidate)
        ->and($user->is_superadmin)->toBeFalse()
        ->and($user->email_verified_at)->toBeNull()
        ->and(Hash::check('motdepasse2026', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyCandidateEmail::class);
});

it('ignores attempts to self-promote at registration', function () {
    $this->post('/inscription', registrationPayload(['type' => 'internal', 'is_superadmin' => 1]));

    $user = User::query()->where('email', 'zeinabou@example.test')->sole();

    expect($user->type)->toBe(UserType::Candidate)->and($user->is_superadmin)->toBeFalse();
});

it('validates registration on the server', function () {
    makeCandidate(['email' => 'taken@example.test']);

    $this->post('/inscription', registrationPayload([
        'email' => 'taken@example.test',
        'password' => 'court',
        'password_confirmation' => 'different',
    ]))->assertSessionHasErrors(['email', 'password']);
});

it('rejects registration bots', function () {
    $this->post('/inscription', registrationPayload([ProtectAgainstSpam::TRAP_FIELD => 'x']))->assertSessionHasErrors('form');

    expect(User::query()->count())->toBe(0);
});

it('requires a verified email for the candidate space', function () {
    $candidate = makeCandidate(['email_verified_at' => null]);

    $this->actingAs($candidate)->get('/espace-candidat')->assertRedirect(route('verification.notice'));

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $candidate->id,
        'hash' => sha1($candidate->email),
    ]);
    $this->actingAs($candidate)->get($url)->assertRedirect(route('candidate.dashboard'));

    expect($candidate->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->actingAs($candidate->fresh())->get('/espace-candidat')->assertOk()->assertSee($candidate->name);
});

it('logs a candidate in and out', function () {
    $candidate = makeCandidate(['email' => 'c@example.test']);

    $this->post('/connexion', ['email' => 'c@example.test', 'password' => 'password'])
        ->assertRedirect(route('candidate.dashboard'));
    $this->assertAuthenticatedAs($candidate);

    $this->post('/deconnexion')->assertRedirect(route('home'));
    $this->assertGuest();
});

it('refuses organisation accounts on the candidate login with the generic message', function () {
    memberOf(makeOrganization(), OrganizationRole::Recruiter, ['email' => 'staff@example.test']);

    $this->post('/connexion', ['email' => 'staff@example.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
    $this->assertGuest();
});

it('limits login attempts', function () {
    makeCandidate(['email' => 'c@example.test']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/connexion', ['email' => 'c@example.test', 'password' => 'wrong']);
    }

    $this->post('/connexion', ['email' => 'c@example.test', 'password' => 'password'])->assertTooManyRequests();
    $this->assertGuest();
});

it('sends a reset link to candidates only, with the same answer for everyone', function () {
    $candidate = makeCandidate(['email' => 'c@example.test']);
    $staff = memberOf(makeOrganization(), OrganizationRole::Jury, ['email' => 's@example.test']);

    foreach (['c@example.test', 's@example.test', 'nobody@example.test'] as $email) {
        $this->post('/mot-de-passe-oublie', ['email' => $email])
            ->assertSessionHas('status', __('candidate.forgot.sent'));
    }

    Notification::assertSentTo($candidate, ResetCandidatePassword::class);
    Notification::assertNotSentTo($staff, ResetCandidatePassword::class);
});

it('resets a candidate password with a valid token', function () {
    $candidate = makeCandidate(['email' => 'c@example.test']);
    $this->post('/mot-de-passe-oublie', ['email' => 'c@example.test']);

    $token = null;
    Notification::assertSentTo($candidate, ResetCandidatePassword::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->post('/reinitialiser-mot-de-passe', [
        'token' => $token,
        'email' => 'c@example.test',
        'password' => 'nouveau2026pass',
        'password_confirmation' => 'nouveau2026pass',
    ])->assertRedirect(route('login'));

    expect(Hash::check('nouveau2026pass', $candidate->fresh()->password))->toBeTrue();
});

it('sends organisation members away from the candidate space', function () {
    $this->actingAs(memberOf(makeOrganization(), OrganizationRole::Recruiter))
        ->get('/espace-candidat')
        ->assertRedirect('/app');
});
