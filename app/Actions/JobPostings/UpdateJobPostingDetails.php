<?php

namespace App\Actions\JobPostings;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\JobPostingStatus;
use App\Exceptions\ActionFailed;
use App\Jobs\GenerateShareImage;
use App\Models\JobPosting;
use App\Models\Organization;
use App\Models\User;
use App\Support\HtmlSanitizer;
use App\Support\JobPostings\JobPostingReference;
use App\Support\JobPostings\JobPostingRules;
use App\Support\JobPostings\JobPostingSlug;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Wizard step 1 on an existing offer. The slug follows the title until the
 * first publication, then never changes (shared links must keep working).
 */
class UpdateJobPostingDetails
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, JobPosting $posting, array $data): JobPosting
    {
        Gate::forUser($actor)->authorize('update', $posting);

        $data = Validator::make($data, JobPostingRules::details())->validate();
        $organization = Organization::query()->findOrFail($posting->organization_id);

        if (filled($data['reference'] ?? null) && JobPostingReference::exists($organization, $data['reference'], $posting->getKey())) {
            throw ActionFailed::because('job_postings.errors.reference_taken');
        }

        return DB::transaction(function () use ($actor, $posting, $data, $organization) {
            $posting->fill(Arr::except($data, ['reference', 'description']));
            $posting->description = $this->sanitizer->clean($data['description'] ?? null);

            if (filled($data['reference'] ?? null)) {
                $posting->reference = $data['reference'];
            }

            if (! $posting->wasEverPublished() && $posting->isDirty('title')) {
                $posting->slug = JobPostingSlug::generate($posting->title, $organization, $posting->getKey());
            }

            $posting->wizard_step = max($posting->wizard_step, 2);
            $changed = array_keys($posting->getDirty());
            $posting->save();

            if (array_intersect($changed, ['title', 'location', 'closes_at']) !== [] && $posting->status === JobPostingStatus::Published) {
                GenerateShareImage::dispatch($posting)->afterCommit();
            }

            if ($changed !== []) {
                $this->audit->handle(
                    AuditEvent::JobPostingUpdated,
                    subject: $posting,
                    causer: $actor,
                    organization: $posting->organization_id,
                    properties: ['changed' => $changed],
                );
            }

            return $posting;
        });
    }
}
