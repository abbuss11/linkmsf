<?php

use App\Enums\OrganizationRole;
use App\Filament\Admin\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\App\Resources\Invitations\Pages\ListInvitations;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MemberInvited;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/*
 * Livrable de la phase 1 : une organisation est créée, son orgadmin invite
 * un recruteur et un membre du jury, chacun ne voit que ce qui le concerne.
 */

function acceptInvitationAs(string $email, string $name): User
{
    $notification = null;
    Notification::assertSentTo(new AnonymousNotifiable, MemberInvited::class,
        function (MemberInvited $sent, array $channels, AnonymousNotifiable $notifiable) use ($email, &$notification) {
            if (($notifiable->routes['mail'] ?? null) === $email) {
                $notification = $sent;
            }

            return true;
        });

    Auth::logout();
    $url = URL::signedRoute('invitations.show', ['invitation' => $notification->invitation->ulid, 'token' => $notification->plainToken]);
    test()->get($url)->assertOk();
    test()->post(route('invitations.accept', $notification->invitation->ulid), [
        'token' => $notification->plainToken,
        'name' => $name,
        'password' => 'motdepasse2026',
        'password_confirmation' => 'motdepasse2026',
    ])->assertRedirect();

    return User::query()->where('email', $email)->sole();
}

it('runs the phase 1 scenario end to end', function () {
    Notification::fake();

    // 1. The superadmin creates the organisation and invites its orgadmin.
    actingOnPlatform(makeSuperadmin());
    Livewire::test(CreateOrganization::class)
        ->fillForm(['name' => 'Sahel Avenir', 'slug' => 'sahel-avenir', 'timezone' => 'Africa/Niamey', 'country' => 'NE', 'admin_email' => 'aicha@sahel.test'])
        ->call('create')
        ->assertHasNoFormErrors();
    $organization = Organization::query()->where('slug', 'sahel-avenir')->sole();

    // 2. The orgadmin accepts, then enables MFA (mandatory for this role).
    $admin = acceptInvitationAs('aicha@sahel.test', 'Aïcha Moussa');
    expect($admin->roleIn($organization))->toBe(OrganizationRole::OrgAdmin);
    $this->actingAs($admin)->get('/app/sahel-avenir')->assertRedirectContains('multi-factor-authentication/set-up');
    $admin->toggleEmailAuthentication(true);

    // 3. The orgadmin invites a recruiter and a jury member.
    actingInOrganization($admin, $organization);
    Livewire::test(ListInvitations::class)
        ->callAction('invite', data: ['email' => 'moussa@sahel.test', 'role' => OrganizationRole::Recruiter->value])
        ->callAction('invite', data: ['email' => 'fatima@sahel.test', 'role' => OrganizationRole::Jury->value])
        ->assertHasNoErrors();

    $recruiter = acceptInvitationAs('moussa@sahel.test', 'Moussa Issoufou');
    $jury = acceptInvitationAs('fatima@sahel.test', 'Fatima Abdou');

    expect($recruiter->roleIn($organization))->toBe(OrganizationRole::Recruiter)
        ->and($jury->roleIn($organization))->toBe(OrganizationRole::Jury);

    // 4. Each one only sees what concerns them. The session is reset between
    // users, as AuthenticateSession ties it to one account's password hash.
    $this->flushSession();
    $this->actingAs($admin->fresh())->get('/app/sahel-avenir/members')->assertOk()
        ->assertSee('Moussa Issoufou')->assertSee('Fatima Abdou');

    foreach ([$recruiter, $jury] as $member) {
        $this->flushSession();
        $this->actingAs($member)->get('/app/sahel-avenir')->assertOk();
        $this->actingAs($member)->get('/app/sahel-avenir/members')->assertForbidden();
        $this->actingAs($member)->get('/app/sahel-avenir/invitations')->assertForbidden();
        $this->actingAs($member)->get('/app/sahel-avenir/activity')->assertForbidden();
        $this->actingAs($member)->get('/admin')->assertForbidden();
    }

    // 5. Nobody sees another organisation.
    $other = makeOrganization(['slug' => 'autre']);
    memberOf($other, OrganizationRole::OrgAdmin);
    foreach ([$admin->fresh(), $recruiter, $jury] as $member) {
        $this->flushSession();
        $this->actingAs($member)->get('/app/autre')->assertNotFound();
    }
});
