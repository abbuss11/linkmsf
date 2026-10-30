<?php

namespace App\Console\Commands;

use App\Actions\JobPostings\CloseJobPosting;
use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\Organization;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Throwable;

/**
 * Closes offers whose deadline has passed (§3.2), every minute. Deadlines
 * are stored in UTC after conversion from the organisation's timezone.
 * Idempotent: an offer already closed is left untouched.
 */
class CloseExpiredJobPostings extends Command
{
    protected $signature = 'job-postings:close-expired';

    protected $description = 'Clôture les offres dont la date limite est passée';

    public function handle(CloseJobPosting $closeJobPosting, OrganizationContext $context): int
    {
        $closed = 0;

        JobPosting::queryAcrossOrganizations()
            ->whereIn('status', [JobPostingStatus::Published->value, JobPostingStatus::Suspended->value])
            ->whereNotNull('closes_at')
            ->where('closes_at', '<=', now())
            ->chunkById(100, function ($postings) use ($closeJobPosting, $context, &$closed): void {
                foreach ($postings as $posting) {
                    $organization = Organization::query()->find($posting->organization_id);

                    if ($organization === null) {
                        continue;
                    }

                    try {
                        $context->runFor($organization, fn () => $closeJobPosting->handle(null, $posting));
                        $closed++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->error("{$posting->reference}: {$exception->getMessage()}");
                    }
                }
            });

        $this->info("{$closed} offre(s) clôturée(s).");

        return self::SUCCESS;
    }
}
