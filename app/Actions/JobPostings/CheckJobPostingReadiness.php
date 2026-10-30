<?php

namespace App\Actions\JobPostings;

use App\Enums\JobPostingStatus;
use App\Forms\Dynamic\RequirementRuleValidator;
use App\Forms\Dynamic\SchemaValidator;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use App\Models\JobRequirement;
use App\Models\Organization;

/**
 * Detects inconsistencies before publication: blocking problems prevent it,
 * warnings are shown for information.
 */
class CheckJobPostingReadiness
{
    public function __construct(
        private readonly SchemaValidator $schemaValidator,
        private readonly RequirementRuleValidator $ruleValidator,
    ) {}

    public function handle(JobPosting $posting, bool $checkActiveLimit = true): ReadinessReport
    {
        $organization = Organization::query()->findOrFail($posting->organization_id);
        $form = ApplicationForm::queryForOrganization($organization)->where('job_posting_id', $posting->getKey())->firstOrFail();
        $schema = $form->draftSchema();
        $requirements = JobRequirement::queryForOrganization($organization)
            ->where('job_posting_id', $posting->getKey())
            ->orderBy('position')
            ->get();

        $blocking = [];
        $warnings = [];

        if ($posting->closes_at === null) {
            $blocking[] = __('job_postings.readiness.no_closing_date');
        } elseif ($posting->closes_at->isPast()) {
            $blocking[] = __('job_postings.readiness.closing_date_past');
        }

        if ($schema->isEmpty()) {
            $blocking[] = __('job_postings.readiness.empty_form');
        }

        array_push($blocking, ...$this->schemaValidator->validate($form->schema, $form->publishedKeyTypes()));

        foreach ($requirements as $requirement) {
            array_push($blocking, ...$this->ruleValidator->validate([
                'label' => $requirement->label,
                'usage' => $requirement->usage->value,
                'field_key' => $requirement->field_key,
                'rule' => $requirement->rule,
            ], $schema, strict: true));
        }

        if ($checkActiveLimit && ! $posting->status->isActive()) {
            $active = JobPosting::queryForOrganization($organization)
                ->whereIn('status', [JobPostingStatus::Published->value, JobPostingStatus::Suspended->value])
                ->whereKeyNot($posting->getKey())
                ->count();

            if ($active >= $organization->limit('max_active_job_postings')) {
                $blocking[] = __('job_postings.readiness.active_limit', ['max' => $organization->limit('max_active_job_postings')]);
            }
        }

        if ($requirements->isEmpty()) {
            $warnings[] = __('job_postings.readiness.no_requirements');
        }

        if ($schema->documentFields() === []) {
            $warnings[] = __('job_postings.readiness.no_documents');
        }

        if (blank($posting->description)) {
            $warnings[] = __('job_postings.readiness.no_description');
        }

        return new ReadinessReport(array_values(array_unique($blocking)), $warnings);
    }
}
