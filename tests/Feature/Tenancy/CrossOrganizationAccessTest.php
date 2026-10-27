<?php

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Members\ChangeMemberRole;
use App\Actions\Members\InviteMember;
use App\Actions\Members\ResendInvitation;
use App\Actions\Members\RevokeInvitation;
use App\Actions\Organizations\UpdateOrganizationProfile;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRole;
use App\Filament\App\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\App\Resources\Invitations\Pages\ListInvitations;
use App\Filament\App\Resources\Members\Pages\ListMembers;
use App\Models\Invitation;
use App\Models\OrganizationMembership;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/*
 * Cahier des charges §2.2 and §11.2: for each resource, an access from
 * another organisation (URL, identifier, Livewire action, domain action)
 * must be refused.
 */

beforeEach(function () {
    $this->orgA = makeOrganization(['slug' => 'org-a']);
    $this->orgB = makeOrganization(['slug' => 'org-b']);

    $this->adminA = memberOf($this->orgA, OrganizationRole::OrgAdmin);
    $this->adminB = memberOf($this->orgB, OrganizationRole::OrgAdmin);
    $this->recruiterB = memberOf($this->orgB, OrganizationRole::Recruiter);

    $this->membershipB = OrganizationMembership::queryForOrganization($this->orgB)
        ->where('user_id', $this->recruiterB->id)->firstOrFail();
    $this->invitationB = Invitation::factory()->create(['organization_id' => $this->orgB->id]);
});

it('refuses every page of another organisation by URL', function (string $path) {
    $this->actingAs($this->adminA)
        ->get("/app/org-b{$path}")
        ->assertNotFound();
})->with([
    'dashboard' => [''],
    'members' => ['/members'],
    'invitations' => ['/invitations'],
    'activity' => ['/activity'],
    'profile' => ['/profile'],
]);

it('serves the same pages for the own organisation', function (string $path) {
    $this->actingAs($this->adminA)
        ->get("/app/org-a{$path}")
        ->assertOk();
})->with([
    'dashboard' => [''],
    'members' => ['/members'],
    'invitations' => ['/invitations'],
    'activity' => ['/activity'],
    'profile' => ['/profile'],
]);

it('never lists members of another organisation', function () {
    actingInOrganization($this->adminA, $this->orgA);

    Livewire::test(ListMembers::class)
        ->assertCanSeeTableRecords(OrganizationMembership::queryForOrganization($this->orgA)->get())
        ->assertCanNotSeeTableRecords(OrganizationMembership::queryForOrganization($this->orgB)->get());
});

it('cannot change the role of a member of another organisation through the table', function () {
    actingInOrganization($this->adminA, $this->orgA);

    try {
        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('changeRole')->table($this->membershipB), data: ['role' => OrganizationRole::OrgAdmin->value]);
    } catch (Throwable) {
        // Filament may refuse to mount the action on an unknown record: that is a refusal too.
    }

    expect($this->membershipB->fresh()->role)->toBe(OrganizationRole::Recruiter);
});

it('never lists invitations of another organisation', function () {
    $invitationA = Invitation::factory()->create(['organization_id' => $this->orgA->id]);
    actingInOrganization($this->adminA, $this->orgA);

    Livewire::test(ListInvitations::class)
        ->assertCanSeeTableRecords([$invitationA])
        ->assertCanNotSeeTableRecords([$this->invitationB]);
});

it('cannot revoke an invitation of another organisation through the table', function () {
    actingInOrganization($this->adminA, $this->orgA);

    try {
        Livewire::test(ListInvitations::class)
            ->callAction(TestAction::make('revoke')->table($this->invitationB));
    } catch (Throwable) {
    }

    expect($this->invitationB->fresh()->revoked_at)->toBeNull();
});

it('never lists audit entries of another organisation or of the platform', function () {
    $audit = app(RecordAuditEvent::class);
    $ownLog = $audit->handle(AuditEvent::OrganizationUpdated, organization: $this->orgA);
    $foreignLog = $audit->handle(AuditEvent::OrganizationUpdated, organization: $this->orgB);
    $platformLog = $audit->handle(AuditEvent::OrganizationRequestSubmitted);

    actingInOrganization($this->adminA, $this->orgA);

    Livewire::test(ListAuditLogs::class)
        ->assertCanSeeTableRecords([$ownLog])
        ->assertCanNotSeeTableRecords([$foreignLog, $platformLog]);
});

it('refuses domain actions on records of another organisation', function (Closure $call) {
    actingInOrganization($this->adminA, $this->orgA);

    $call->call($this);
})->with([
    'change role' => [fn () => app(ChangeMemberRole::class)->handle($this->adminA, $this->membershipB, OrganizationRole::Jury)],
    'invite into another organisation' => [fn () => app(InviteMember::class)->handle($this->adminA, $this->orgB, 'x@example.test', OrganizationRole::Jury)],
    'resend invitation' => [fn () => app(ResendInvitation::class)->handle($this->adminA, $this->invitationB)],
    'revoke invitation' => [fn () => app(RevokeInvitation::class)->handle($this->adminA, $this->invitationB)],
    'edit organisation profile' => [fn () => app(UpdateOrganizationProfile::class)->handle($this->adminA, $this->orgB, ['name' => 'Hacked', 'timezone' => 'UTC'])],
])->throws(AuthorizationException::class);

it('keeps records of another organisation unchanged after refused actions', function () {
    actingInOrganization($this->adminA, $this->orgA);

    rescue(fn () => app(ChangeMemberRole::class)->handle($this->adminA, $this->membershipB, OrganizationRole::Jury), report: false);
    rescue(fn () => app(UpdateOrganizationProfile::class)->handle($this->adminA, $this->orgB, ['name' => 'Hacked', 'timezone' => 'UTC']), report: false);

    expect($this->membershipB->fresh()->role)->toBe(OrganizationRole::Recruiter)
        ->and($this->orgB->fresh()->name)->not->toBe('Hacked');
});
