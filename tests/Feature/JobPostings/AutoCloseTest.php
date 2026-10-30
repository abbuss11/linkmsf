<?php

use App\Actions\JobPostings\PublishJobPosting;
use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Enums\OrganizationRole;
use App\Filament\App\Resources\JobPostings\Pages\CreateJobPosting;
use App\Models\AuditLog;
use App\Models\JobPosting;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

it('closes offers whose deadline has passed, across organisations, and only those', function () {
    $orgA = makeOrganization();
    $orgB = makeOrganization();
    $a = memberOf($orgA, OrganizationRole::Recruiter);
    $b = memberOf($orgB, OrganizationRole::Recruiter);

    $expiredA = draftPosting($a, $orgA);
    $expiredB = draftPosting($b, $orgB);
    $stillOpen = draftPosting($a, $orgA, ['closes_at' => now()->addDays(30)->toDateTimeString()]);

    foreach ([[$a, $orgA, $expiredA], [$b, $orgB, $expiredB], [$a, $orgA, $stillOpen]] as [$user, $org, $posting]) {
        actingInOrganization($user, $org);
        app(PublishJobPosting::class)->handle($user, $posting);
    }
    // The scheduler runs outside any request: no organisation, no Filament tenant.
    app(OrganizationContext::class)->clear();
    Filament\Facades\Filament::setTenant(null);

    Carbon::setTestNow(now()->addDays(11));

    $this->artisan('job-postings:close-expired')->expectsOutputToContain('2 offre(s)')->assertSuccessful();
    // Idempotent: a second run changes nothing.
    $this->artisan('job-postings:close-expired')->expectsOutputToContain('0 offre(s)')->assertSuccessful();

    expect($expiredA->fresh()->status)->toBe(JobPostingStatus::Closed)
        ->and($expiredB->fresh()->status)->toBe(JobPostingStatus::Closed)
        ->and($stillOpen->fresh()->status)->toBe(JobPostingStatus::Published)
        ->and(AuditLog::query()->where('event', AuditEvent::JobPostingClosed->value)->count())->toBe(2)
        ->and(AuditLog::query()->where('event', AuditEvent::JobPostingClosed->value)->first()->properties['automatic'])->toBeTrue();

    Carbon::setTestNow();
});

it('reads the deadline typed in the organisation timezone', function () {
    // 18:00 in Niamey (UTC+1) is 17:00 UTC.
    $org = makeOrganization(['timezone' => 'Africa/Niamey']);
    $recruiter = memberOf($org, OrganizationRole::Recruiter);
    actingInOrganization($recruiter, $org);

    $livewire = Livewire\Livewire::test(CreateJobPosting::class)
        ->fillForm(['type' => 'employment', 'title' => 'Comptable', 'closes_at' => '2030-03-10 18:00:00'])
        ->call('create')
        ->assertHasNoFormErrors();

    $posting = JobPosting::query()->sole();

    expect($posting->closes_at->utc()->format('Y-m-d H:i'))->toBe('2030-03-10 17:00');
});

it('is scheduled every minute without overlap', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'job-postings:close-expired'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
