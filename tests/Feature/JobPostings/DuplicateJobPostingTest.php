<?php

use App\Actions\JobPostings\DuplicateJobPosting;
use App\Actions\JobPostings\PublishJobPosting;
use App\Actions\JobPostings\SaveJobRequirements;
use App\Enums\JobPostingStatus;
use App\Enums\OrganizationRole;
use App\Models\ApplicationForm;
use App\Models\JobRequirement;

it('copies criteria and form into a new draft without dates or published version', function () {
    $org = makeOrganization();
    $recruiter = memberOf($org, OrganizationRole::Recruiter);
    $source = draftPosting($recruiter, $org);
    actingInOrganization($recruiter, $org);

    app(SaveJobRequirements::class)->handle($recruiter, $source, [
        ['label' => 'Niveau Licence', 'usage' => 'eliminatory', 'field_key' => 'niveau_detudes', 'rule' => ['operator' => 'education_at_least', 'value' => 'licence']],
        ['label' => 'Expérience terrain', 'usage' => 'scored', 'weight' => 40],
    ]);
    app(PublishJobPosting::class)->handle($recruiter, $source->fresh());

    $copy = app(DuplicateJobPosting::class)->handle($recruiter, $source->fresh());
    $copyForm = ApplicationForm::query()->where('job_posting_id', $copy->id)->sole();
    $sourceForm = ApplicationForm::query()->where('job_posting_id', $source->id)->sole();

    expect($copy->status)->toBe(JobPostingStatus::Draft)
        ->and($copy->closes_at)->toBeNull()
        ->and($copy->published_at)->toBeNull()
        ->and($copy->reference)->not->toBe($source->reference)
        ->and($copy->slug)->not->toBe($source->slug)
        ->and($copyForm->schema)->toEqual($sourceForm->schema)
        ->and($copyForm->current_version_id)->toBeNull()
        ->and(JobRequirement::query()->where('job_posting_id', $copy->id)->pluck('label')->all())->toBe(['Niveau Licence', 'Expérience terrain'])
        ->and($copy->isAssignedTo($recruiter))->toBeTrue();
});
