<?php

use App\Actions\Members\InviteMember;
use App\Actions\Members\ResendInvitation;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRole;
use App\Enums\UserType;
use App\Exceptions\ActionFailed;
use App\Filament\App\Resources\Invitations\Pages\ListInvitations;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\InvitationAccepted;
use App\Notifications\MemberInvited;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->org = makeOrganization(['slug' => 'sahel', 'name' => 'Sahel Avenir']);
    $this->admin = memberOf($this->org, OrganizationRole::OrgAdmin);
});

function invitationUrl(Invitation $invitation, string $token): string
{
    return URL::signedRoute('invitations.show', [
        'invitation' => $invitation->ulid,
        'token' => $token,
    ]);
}

function sentToken(string $email): string
{
    $token = null;

    Notification::assertSentTo(
        new AnonymousNotifiable,
        MemberInvited::class,
        function (MemberInvited $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$token) {
            if (($notifiable->routes['mail'] ?? null) !== $email) {
                return false;
            }
            $token = $notification->plainToken;

            return true;
        },
    );

    return $token;
}

it('lets an orgadmin invite a member from the back-office', function () {
    actingInOrganization($this->admin, $this->org);

    Livewire::test(ListInvitations::class)
        ->callAction('invite', data: ['email' => 'New.Recruiter@Example.test', 'role' => OrganizationRole::Recruiter->value])
        ->assertHasNoErrors()
        ->assertNotified();

    $invitation = Invitation::queryForOrganization($this->org)->sole();

    expect($invitation->email)->toBe('new.recruiter@example.test')
        ->and($invitation->role)->toBe(OrganizationRole::Recruiter)
        ->and($invitation->invited_by)->toBe($this->admin->id)
        ->and($invitation->token_hash)->toHaveLength(64)
        ->and(AuditLog::query()->where('event', AuditEvent::InvitationSent->value)->where('organization_id', $this->org->id)->exists())->toBeTrue();

    sentToken('new.recruiter@example.test');
});

it('stores only a hash of the invitation token', function () {
    app(InviteMember::class)->handle($this->admin, $this->org, 'x@example.test', OrganizationRole::Jury);

    $token = sentToken('x@example.test');
    $invitation = Invitation::queryForOrganization($this->org)->sole();

    expect($invitation->token_hash)->not->toBe($token)
        ->and($invitation->matchesToken($token))->toBeTrue()
        ->and(AuditLog::query()->get()->pluck('properties')->toJson())->not->toContain($token);
});

it('refuses invitations from recruiters and jury members', function (OrganizationRole $role) {
    $member = memberOf($this->org, $role);

    app(InviteMember::class)->handle($member, $this->org, 'x@example.test', OrganizationRole::Jury);
})->with([OrganizationRole::Recruiter, OrganizationRole::Jury])->throws(AuthorizationException::class);

it('refuses a second pending invitation for the same address', function () {
    app(InviteMember::class)->handle($this->admin, $this->org, 'x@example.test', OrganizationRole::Jury);

    expect(fn () => app(InviteMember::class)->handle($this->admin, $this->org, 'X@example.test', OrganizationRole::Recruiter))
        ->toThrow(ActionFailed::class, __('invitations.errors.already_invited'));
});

it('refuses to invite an existing member', function () {
    expect(fn () => app(InviteMember::class)->handle($this->admin, $this->org, $this->admin->email, OrganizationRole::Jury))
        ->toThrow(ActionFailed::class, __('invitations.errors.already_member'));
});

it('refuses to invite a candidate account', function () {
    $candidate = makeCandidate();

    expect(fn () => app(InviteMember::class)->handle($this->admin, $this->org, $candidate->email, OrganizationRole::Jury))
        ->toThrow(ActionFailed::class, __('invitations.errors.email_is_candidate'));
});

it('lets a new person create an account from the invitation link', function () {
    $other = memberOf($this->org, OrganizationRole::OrgAdmin);
    $invitation = app(InviteMember::class)->handle($this->admin, $this->org, 'jury@example.test', OrganizationRole::Jury);
    $token = sentToken('jury@example.test');

    $this->get(invitationUrl($invitation, $token))
        ->assertOk()
        ->assertSee(__('invitations.page.accept_and_create'));

    $this->post(route('invitations.accept', $invitation->ulid), [
        'token' => $token,
        'name' => 'Fatima Abdou',
        'password' => 'motdepasse2026',
        'password_confirmation' => 'motdepasse2026',
    ])->assertRedirect('/app/sahel');

    $user = User::query()->where('email', 'jury@example.test')->sole();

    expect($user->type)->toBe(UserType::Internal)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->roleIn($this->org))->toBe(OrganizationRole::Jury)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo([$this->admin, $other], InvitationAccepted::class);
    Notification::assertNotSentTo($user, InvitationAccepted::class);
});

it('asks an existing member of another organisation to log in, then accepts', function () {
    $existing = memberOf(makeOrganization(), OrganizationRole::Recruiter, ['email' => 'known@example.test']);
    $invitation = app(InviteMember::class)->handle($this->admin, $this->org, 'known@example.test', OrganizationRole::Recruiter);
    $token = sentToken('known@example.test');

    $this->get(invitationUrl($invitation, $token))->assertSee(__('invitations.page.login'));

    // A guest cannot take over an existing account by "registering" it.
    $this->post(route('invitations.accept', $invitation->ulid), [
        'token' => $token, 'name' => 'X', 'password' => 'motdepasse2026', 'password_confirmation' => 'motdepasse2026',
    ])->assertSessionHasErrors('invitation');
    expect($existing->fresh()->roleIn($this->org))->toBeNull();

    $this->actingAs($existing)->get(invitationUrl($invitation, $token))->assertSee(__('invitations.page.accept'));
    $this->actingAs($existing)->post(route('invitations.accept', $invitation->ulid), ['token' => $token])
        ->assertRedirect('/app/sahel');

    expect($existing->fresh()->roleIn($this->org))->toBe(OrganizationRole::Recruiter);
});

it('refuses the invitation to a user logged in with another account', function () {
    $invitation = app(InviteMember::class)->handle($this->admin, $this->org, 'invited@example.test', OrganizationRole::Jury);
    $token = sentToken('invited@example.test');
    $intruder = memberOf(makeOrganization(), OrganizationRole::Recruiter);

    $this->actingAs($intruder)->get(invitationUrl($invitation, $token))
        ->assertSee(__('invitations.page.wrong_account', ['email' => 'invited@example.test']));

    $this->actingAs($intruder)->post(route('invitations.accept', $invitation->ulid), ['token' => $token])
        ->assertSessionHasErrors('invitation');

    expect($intruder->fresh()->roleIn($this->org))->toBeNull();
});

it('rejects a wrong token, an expired, a revoked or an already used invitation', function (Closure $prepare) {
    $plain = str_repeat('a', 64);
    $invitation = Invitation::factory()->withToken($plain)->create(['organization_id' => $this->org->id, 'email' => 'z@example.test']);
    $token = $prepare($invitation, $plain);

    $this->get(invitationUrl($invitation->fresh(), $token))->assertSee(__('invitations.page.invalid'));

    $this->post(route('invitations.accept', $invitation->ulid), [
        'token' => $token, 'name' => 'Z', 'password' => 'motdepasse2026', 'password_confirmation' => 'motdepasse2026',
    ])->assertSessionHasErrors('invitation');

    expect(OrganizationMembership::queryForOrganization($this->org)->count())->toBe(1);
})->with([
    'wrong token' => [fn (Invitation $i, string $plain) => str_repeat('b', 64)],
    'expired' => [function (Invitation $i, string $plain) {
        $i->forceFill(['expires_at' => now()->subMinute()])->save();

        return $plain;
    }],
    'revoked' => [function (Invitation $i, string $plain) {
        $i->forceFill(['revoked_at' => now()])->save();

        return $plain;
    }],
    'already used' => [function (Invitation $i, string $plain) {
        $i->forceFill(['accepted_at' => now()])->save();

        return $plain;
    }],
]);

it('refuses an unsigned invitation link', function () {
    $invitation = Invitation::factory()->create(['organization_id' => $this->org->id]);

    $this->get('/invitations/'.$invitation->ulid.'?token=abc')->assertForbidden();
});

it('refuses invitations into a suspended organisation', function () {
    $invitation = app(InviteMember::class)->handle($this->admin, $this->org, 'late@example.test', OrganizationRole::Jury);
    $token = sentToken('late@example.test');
    $this->org->update(['status' => 'suspended']);

    $this->get(invitationUrl($invitation, $token))->assertSee(__('invitations.page.invalid'));
});

it('invalidates the previous link when an invitation is resent', function () {
    $invitation = app(InviteMember::class)->handle($this->admin, $this->org, 'again@example.test', OrganizationRole::Jury);
    $firstToken = sentToken('again@example.test');

    Notification::fake();
    actingInOrganization($this->admin, $this->org);
    app(ResendInvitation::class)->handle($this->admin, $invitation);
    $secondToken = sentToken('again@example.test');

    expect($invitation->fresh()->matchesToken($firstToken))->toBeFalse()
        ->and($invitation->fresh()->matchesToken($secondToken))->toBeTrue();
});

it('lets a candidate account see that it cannot join', function () {
    $invitation = Invitation::factory()->withToken(str_repeat('c', 64))->create([
        'organization_id' => $this->org->id,
        'email' => 'cand@example.test',
    ]);
    $candidate = makeCandidate(['email' => 'cand@example.test']);

    $this->actingAs($candidate)->get(invitationUrl($invitation, str_repeat('c', 64)))
        ->assertSee(__('invitations.errors.email_is_candidate'));
});
