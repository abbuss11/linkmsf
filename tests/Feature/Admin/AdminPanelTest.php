<?php

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Admin\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Admin\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Admin\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\Invitation;
use App\Models\Organization;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->superadmin = makeSuperadmin();
    actingOnPlatform($this->superadmin);
});

it('creates an organisation and invites its first orgadmin', function () {
    Livewire::test(CreateOrganization::class)
        ->fillForm([
            'name' => 'Sahel Avenir',
            'slug' => 'sahel-avenir',
            'timezone' => 'Africa/Niamey',
            'country' => 'NE',
            'admin_email' => 'admin@sahel.test',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $organization = Organization::query()->where('slug', 'sahel-avenir')->sole();

    expect($organization->status)->toBe(OrganizationStatus::Active)
        ->and($organization->limit('max_active_job_postings'))->toBe(Organization::DEFAULT_LIMITS['max_active_job_postings'])
        ->and(Invitation::queryForOrganization($organization)->sole()->role)->toBe(OrganizationRole::OrgAdmin);
});

it('edits limits and slug of an organisation', function () {
    $organization = makeOrganization(['slug' => 'ancien']);

    Livewire::test(EditOrganization::class, ['record' => $organization->slug])
        ->fillForm([
            'slug' => 'nouveau',
            'limits' => ['max_active_job_postings' => 5, 'storage_quota_mb' => 100],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($organization->fresh()->slug)->toBe('nouveau')
        ->and($organization->fresh()->limit('max_active_job_postings'))->toBe(5)
        ->and($organization->fresh()->limit('storage_quota_mb'))->toBe(100);
});

it('suspends and reactivates an organisation', function () {
    $organization = makeOrganization();

    Livewire::test(ListOrganizations::class)->callAction(TestAction::make('suspend')->table($organization));
    expect($organization->fresh()->status)->toBe(OrganizationStatus::Suspended);

    Livewire::test(ListOrganizations::class)->callAction(TestAction::make('activate')->table($organization->fresh()));
    expect($organization->fresh()->status)->toBe(OrganizationStatus::Active);
});

it('counts members across organisations on the platform list', function () {
    $organization = makeOrganization();
    memberOf($organization, OrganizationRole::Recruiter);
    memberOf($organization, OrganizationRole::Jury);

    Livewire::test(ListOrganizations::class)
        ->assertCanSeeTableRecords([$organization])
        ->assertTableColumnStateSet('memberships_count', 2, $organization);
});

it('shows the audit log of every organisation and of the platform', function () {
    $audit = app(RecordAuditEvent::class);
    $orgLog = $audit->handle(AuditEvent::OrganizationUpdated, organization: makeOrganization());
    $platformLog = $audit->handle(AuditEvent::OrganizationRequestSubmitted);

    Livewire::test(ListAuditLogs::class)->assertCanSeeTableRecords([$orgLog, $platformLog]);
});

it('lists users of every type', function () {
    $candidate = makeCandidate();
    $member = memberOf(makeOrganization(), OrganizationRole::Jury);

    Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$candidate, $member, $this->superadmin]);
});
