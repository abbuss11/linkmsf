<?php

use App\Actions\Applications\StartApplication;
use App\Enums\OrganizationRole;
use App\Notifications\DraftDeadlineReminder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('reminds unfinished applications once, 48 hours before the deadline', function () {
    Storage::fake('public');
    Notification::fake();
    $org = makeOrganization(['slug' => 'sahel']);
    $recruiter = memberOf($org, OrganizationRole::Recruiter);
    $soon = openPosting($recruiter, $org, ['title' => 'Bientôt clos', 'closes_at' => now()->addHours(30)->toDateTimeString()]);
    $later = openPosting($recruiter, $org, ['title' => 'Plus tard', 'closes_at' => now()->addDays(10)->toDateTimeString()]);

    $candidate = makeCandidate();
    app(StartApplication::class)->handle($candidate, $soon);
    app(StartApplication::class)->handle($candidate, $later);

    $this->artisan('applications:remind-drafts')->assertSuccessful();
    $this->artisan('applications:remind-drafts')->assertSuccessful();

    Notification::assertSentToTimes($candidate, DraftDeadlineReminder::class, 1);
    Notification::assertSentTo($candidate, DraftDeadlineReminder::class, fn ($n) => $n->postingTitle === 'Bientôt clos');
});
