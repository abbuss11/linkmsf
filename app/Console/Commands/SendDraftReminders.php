<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Models\Application;
use App\Models\Organization;
use App\Notifications\DraftDeadlineReminder;
use Illuminate\Console\Command;

/**
 * Reminds candidates of unfinished applications 48 hours before the
 * deadline (§4.5). Idempotent: draft_reminded_at marks reminded drafts.
 */
class SendDraftReminders extends Command
{
    protected $signature = 'applications:remind-drafts';

    protected $description = 'Rappelle les brouillons de candidature avant la date limite';

    public function handle(): int
    {
        $hours = (int) config('link.applications.draft_reminder_hours');
        $sent = 0;

        Application::queryAcrossOrganizations()
            ->where('status', ApplicationStatus::Draft->value)
            ->whereNull('draft_reminded_at')
            ->whereNotNull('candidate_user_id')
            ->whereHas('jobPosting', fn ($query) => $query
                ->where('status', JobPostingStatus::Published->value)
                ->where('closes_at', '>', now())
                ->where('closes_at', '<=', now()->addHours($hours)))
            ->with(['jobPosting', 'candidate'])
            ->chunkById(200, function ($applications) use (&$sent): void {
                foreach ($applications as $application) {
                    $organization = Organization::query()->find($application->organization_id);

                    if ($application->candidate === null || $organization === null) {
                        continue;
                    }

                    $application->forceFill(['draft_reminded_at' => now()])->save();
                    $application->candidate->notify(new DraftDeadlineReminder(
                        $application->ulid,
                        $application->jobPosting->title,
                        $organization->name,
                        $application->jobPosting->closes_at?->timezone($organization->timezone)->translatedFormat('j F Y à H:i') ?? '',
                    ));
                    $sent++;
                }
            });

        $this->info("{$sent} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
