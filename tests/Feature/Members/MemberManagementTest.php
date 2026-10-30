<?php

use App\Actions\Members\ChangeMemberRole;
use App\Actions\Members\ChangeMemberStatus;
use App\Actions\Members\RemoveMember;
use App\Enums\AuditEvent;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Exceptions\ActionFailed;
use App\Filament\App\Resources\Members\Pages\ListMembers;
use App\Models\AuditLog;
use App\Models\OrganizationMembership;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = makeOrganization(['slug' => 'sahel']);
    $this->admin = memberOf($this->org, OrganizationRole::OrgAdmin);
    $this->recruiter = memberOf($this->org, OrganizationRole::Recruiter);
    $this->membership = fn (User $user) => OrganizationMembership::queryForOrganization($this->org)
        ->where('user_id', $user->id)->firstOrFail();
});

it('changes the role of a member from the back-office and audits it', function () {
    actingInOrganization($this->admin, $this->org);
    $membership = ($this->membership)($this->recruiter);

    Livewire::test(ListMembers::class)
        ->callAction(TestAction::make('changeRole')->table($membership), data: ['role' => OrganizationRole::Jury->value])
        ->assertNotified(__('members.notifications.role_changed'));

    expect($membership->fresh()->role)->toBe(OrganizationRole::Jury);

    $log = AuditLog::query()->where('event', AuditEvent::MemberRoleChanged->value)->sole();
    expect($log->organization_id)->toBe($this->org->id)
        ->and($log->causer_id)->toBe($this->admin->id)
        ->and($log->properties['from'])->toBe('recruiter')
        ->and($log->properties['to'])->toBe('jury');
});

it('does not let anyone change their own role', function () {
    actingInOrganization($this->admin, $this->org);

    app(ChangeMemberRole::class)->handle($this->admin, ($this->membership)($this->admin), OrganizationRole::Jury);
})->throws(AuthorizationException::class);

it('does not let a recruiter manage members', function () {
    $jury = memberOf($this->org, OrganizationRole::Jury);
    actingInOrganization($this->recruiter, $this->org);

    app(ChangeMemberRole::class)->handle($this->recruiter, ($this->membership)($jury), OrganizationRole::OrgAdmin);
})->throws(AuthorizationException::class);

it('never leaves an organisation without an active orgadmin', function (Closure $operation) {
    // Policies already stop admins from managing their own seat. To test the
    // business rule on its own (e.g. two admins demoting each other at the
    // same time), authorisation is granted and the last admin is targeted.
    Gate::before(fn () => true);
    actingInOrganization($this->recruiter, $this->org);
    $last = ($this->membership)($this->admin);

    expect(fn () => $operation($this->recruiter, $last))
        ->toThrow(ActionFailed::class, __('members.errors.last_admin'));

    expect($last->fresh()->role)->toBe(OrganizationRole::OrgAdmin)
        ->and($last->fresh()->status)->toBe(MembershipStatus::Active);
})->with([
    'demote' => [fn (User $actor, OrganizationMembership $last) => app(ChangeMemberRole::class)->handle($actor, $last, OrganizationRole::Jury)],
    'suspend' => [fn (User $actor, OrganizationMembership $last) => app(ChangeMemberStatus::class)->handle($actor, $last, MembershipStatus::Suspended)],
    'remove' => [fn (User $actor, OrganizationMembership $last) => app(RemoveMember::class)->handle($actor, $last)],
]);

it('does not count suspended orgadmins as remaining admins', function () {
    $suspendedAdmin = memberOf($this->org, OrganizationRole::OrgAdmin);
    ($this->membership)($suspendedAdmin)->update(['status' => MembershipStatus::Suspended]);
    Gate::before(fn () => true);
    actingInOrganization($this->recruiter, $this->org);

    expect(fn () => app(ChangeMemberRole::class)->handle($this->recruiter, ($this->membership)($this->admin), OrganizationRole::Jury))
        ->toThrow(ActionFailed::class, __('members.errors.last_admin'));
});

it('lets an orgadmin demote another orgadmin when one admin remains', function () {
    $otherAdmin = memberOf($this->org, OrganizationRole::OrgAdmin);
    actingInOrganization($this->admin, $this->org);

    app(ChangeMemberRole::class)->handle($this->admin, ($this->membership)($otherAdmin), OrganizationRole::Recruiter);

    expect(($this->membership)($otherAdmin)->role)->toBe(OrganizationRole::Recruiter);
});

it('suspends a member, who then loses access, and reactivates them', function () {
    actingInOrganization($this->admin, $this->org);
    $membership = ($this->membership)($this->recruiter);

    Livewire::test(ListMembers::class)->callAction(TestAction::make('suspend')->table($membership));
    expect($membership->fresh()->status)->toBe(MembershipStatus::Suspended);

    $this->actingAs($this->recruiter->fresh())->get('/app/sahel')->assertNotFound();

    actingInOrganization($this->admin, $this->org);
    Livewire::test(ListMembers::class)->callAction(TestAction::make('reactivate')->table($membership->fresh()));
    expect($membership->fresh()->status)->toBe(MembershipStatus::Active);
});

it('removes a member without deleting their account', function () {
    actingInOrganization($this->admin, $this->org);
    $membership = ($this->membership)($this->recruiter);

    Livewire::test(ListMembers::class)->callAction(TestAction::make('remove')->table($membership));

    expect(OrganizationMembership::queryAcrossOrganizations()->find($membership->id))->toBeNull()
        ->and(User::query()->find($this->recruiter->id))->not->toBeNull()
        ->and(AuditLog::query()->where('event', AuditEvent::MemberRemoved->value)->exists())->toBeTrue();
});

it('does not offer member actions on the own row', function () {
    actingInOrganization($this->admin, $this->org);

    Livewire::test(ListMembers::class)
        ->assertActionHidden(TestAction::make('changeRole')->table(($this->membership)($this->admin)))
        ->assertActionHidden(TestAction::make('remove')->table(($this->membership)($this->admin)));
});
