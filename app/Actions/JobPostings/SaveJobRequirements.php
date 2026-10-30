<?php

namespace App\Actions\JobPostings;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Enums\RequirementUsage;
use App\Forms\Dynamic\RequirementRuleValidator;
use App\Models\ApplicationForm;
use App\Models\JobPosting;
use App\Models\JobRequirement;
use App\Models\User;
use App\Support\JobPostings\JobPostingRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Wizard step 2: replaces the criteria of an offer. Links to form fields are
 * checked now; completeness of eliminatory criteria is checked at publication.
 */
class SaveJobRequirements
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
        private readonly RequirementRuleValidator $ruleValidator,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $requirements
     */
    public function handle(User $actor, JobPosting $posting, array $requirements): JobPosting
    {
        Gate::forUser($actor)->authorize('update', $posting);

        $requirements = Validator::make(['requirements' => $requirements], JobPostingRules::requirements())
            ->validate()['requirements'];

        $schema = ApplicationForm::queryForOrganization($posting->organization()->firstOrFail())
            ->where('job_posting_id', $posting->getKey())
            ->firstOrFail()
            ->draftSchema();

        $errors = [];
        foreach ($requirements as $requirement) {
            array_push($errors, ...$this->ruleValidator->validate($requirement, $schema));
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['requirements' => $errors]);
        }

        return DB::transaction(function () use ($actor, $posting, $requirements) {
            JobRequirement::queryForOrganization($posting->organization()->firstOrFail())
                ->where('job_posting_id', $posting->getKey())
                ->delete();

            foreach (array_values($requirements) as $position => $requirement) {
                $usage = RequirementUsage::from($requirement['usage']);

                JobRequirement::create([
                    'organization_id' => $posting->organization_id,
                    'job_posting_id' => $posting->getKey(),
                    'label' => $requirement['label'],
                    'description' => $requirement['description'] ?? null,
                    'usage' => $usage,
                    'field_key' => filled($requirement['field_key'] ?? null) ? $requirement['field_key'] : null,
                    'rule' => filled($requirement['rule']['operator'] ?? null) ? $requirement['rule'] : null,
                    'weight' => $usage->hasWeight() ? ($requirement['weight'] ?? null) : null,
                    'position' => $position,
                ]);
            }

            $posting->wizard_step = max($posting->wizard_step, 3);
            $posting->save();

            $this->audit->handle(
                AuditEvent::JobRequirementsUpdated,
                subject: $posting,
                causer: $actor,
                organization: $posting->organization_id,
                properties: ['count' => count($requirements)],
            );

            return $posting;
        });
    }
}
