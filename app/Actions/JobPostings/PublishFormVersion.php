<?php

namespace App\Actions\JobPostings;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobPosting;
use App\Models\User;

/**
 * Freezes the working copy of the form as a new version, only when it
 * differs from the current one (§3.4). Internal building block: callers
 * authorise and open the transaction.
 */
class PublishFormVersion
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function handle(JobPosting $posting, ?User $actor): ApplicationFormVersion
    {
        $form = ApplicationForm::queryForOrganization($posting->organization()->firstOrFail())
            ->where('job_posting_id', $posting->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $schema = $form->draftSchema();
        $current = $form->currentVersion;

        if ($current !== null && $current->schema_hash === $schema->hash()) {
            return $current;
        }

        $version = ApplicationFormVersion::create([
            'organization_id' => $form->organization_id,
            'application_form_id' => $form->getKey(),
            'version' => (int) $form->versions()->max('version') + 1,
            'schema' => $schema->toArray(),
            'schema_hash' => $schema->hash(),
            'published_at' => now(),
            'published_by' => $actor?->getKey(),
        ]);

        $form->current_version_id = $version->getKey();
        $form->save();

        $this->audit->handle(
            AuditEvent::ApplicationFormVersionPublished,
            subject: $posting,
            causer: $actor,
            organization: $posting->organization_id,
            properties: ['version' => $version->version],
        );

        return $version;
    }
}
