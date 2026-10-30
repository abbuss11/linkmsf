<?php

namespace App\Actions\JobPostings;

use App\Enums\JobPostingStatus;
use App\Exceptions\ActionFailed;
use App\Forms\Dynamic\RequirementRuleValidator;
use App\Forms\Dynamic\SchemaValidator;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormVersion;
use App\Models\JobPosting;
use App\Models\JobRequirement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Publishes form changes on an offer that is already live: creates a new
 * version; applications already submitted keep theirs (§3.4).
 */
class PublishFormChanges
{
    public function __construct(
        private readonly PublishFormVersion $publishFormVersion,
        private readonly SchemaValidator $schemaValidator,
        private readonly RequirementRuleValidator $ruleValidator,
    ) {}

    public function handle(User $actor, JobPosting $posting): ApplicationFormVersion
    {
        Gate::forUser($actor)->authorize('update', $posting);

        if (! in_array($posting->status, [JobPostingStatus::Published, JobPostingStatus::Suspended, JobPostingStatus::Closed], true)) {
            throw ActionFailed::because('job_postings.errors.not_live');
        }

        $organization = $posting->organization()->firstOrFail();
        $form = ApplicationForm::queryForOrganization($organization)->where('job_posting_id', $posting->getKey())->firstOrFail();
        $schema = $form->draftSchema();

        $problems = $this->schemaValidator->validate($form->schema, $form->publishedKeyTypes());

        if ($schema->isEmpty()) {
            $problems[] = __('job_postings.readiness.empty_form');
        }

        foreach (JobRequirement::queryForOrganization($organization)->where('job_posting_id', $posting->getKey())->get() as $requirement) {
            array_push($problems, ...$this->ruleValidator->validate(
                ['label' => $requirement->label, 'usage' => $requirement->usage->value, 'field_key' => $requirement->field_key, 'rule' => $requirement->rule],
                $schema,
                strict: true,
            ));
        }

        if ($problems !== []) {
            throw ActionFailed::because('job_postings.errors.not_ready', ['problems' => implode(' ', $problems)]);
        }

        return DB::transaction(fn () => $this->publishFormVersion->handle($posting, $actor));
    }
}
