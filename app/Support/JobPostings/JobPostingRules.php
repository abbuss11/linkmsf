<?php

namespace App\Support\JobPostings;

use App\Enums\ContractType;
use App\Enums\DuplicatePolicy;
use App\Enums\EditAfterSubmitPolicy;
use App\Enums\EducationLevel;
use App\Enums\JobPostingType;
use App\Enums\RequirementUsage;
use Illuminate\Validation\Rule;

/**
 * Validation rules shared by the Actions (server truth) and the wizard steps.
 */
class JobPostingRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function details(): array
    {
        return [
            'type' => ['required', Rule::enum(JobPostingType::class)],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'reference' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9\/_-]*$/'],
            'description' => ['nullable', 'string', 'max:100000'],
            'missions' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'contract_type' => ['nullable', Rule::enum(ContractType::class)],
            'education_level' => ['nullable', Rule::enum(EducationLevel::class)],
            'experience_years_min' => ['nullable', 'integer', 'min:0', 'max:50'],
            'skills' => ['nullable', 'array', 'max:30'],
            'skills.*' => ['string', 'max:60'],
            'places' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function settings(): array
    {
        $allowed = (array) config('link.uploads.allowed_extensions');

        return [
            'edit_after_submit' => ['required', Rule::enum(EditAfterSubmitPolicy::class)],
            'duplicate_policy' => ['required', Rule::enum(DuplicatePolicy::class)],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'confirmation_message' => ['nullable', 'string', 'max:2000'],
            'notify_new_applications' => ['required', Rule::in(['none', 'each', 'daily'])],
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*.label' => ['required', 'string', 'max:255'],
            'documents.*.key' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_]{0,49}$/'],
            'documents.*.help' => ['nullable', 'string', 'max:500'],
            'documents.*.required' => ['boolean'],
            'documents.*.extensions' => ['required', 'array', 'min:1'],
            'documents.*.extensions.*' => [Rule::in($allowed)],
            'documents.*.max_kb' => ['required', 'integer', 'min:100', 'max:'.(int) config('link.uploads.max_kb_cap')],
            'documents.*.max_files' => ['required', 'integer', 'min:1', 'max:'.(int) config('link.uploads.max_files_cap')],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function requirements(): array
    {
        return [
            'requirements' => ['present', 'array', 'max:50'],
            'requirements.*.label' => ['required', 'string', 'max:255'],
            'requirements.*.description' => ['nullable', 'string', 'max:2000'],
            'requirements.*.usage' => ['required', Rule::enum(RequirementUsage::class)],
            'requirements.*.field_key' => ['nullable', 'string', 'max:50'],
            'requirements.*.rule' => ['nullable', 'array'],
            'requirements.*.weight' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
