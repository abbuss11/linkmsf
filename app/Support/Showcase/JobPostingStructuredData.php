<?php

namespace App\Support\Showcase;

use App\Enums\ContractType;
use App\Models\JobPosting;
use App\Models\Organization;
use Illuminate\Support\Facades\Storage;

/**
 * schema.org JobPosting for Google Jobs (§4.1).
 */
class JobPostingStructuredData
{
    public static function json(JobPosting $posting, Organization $organization): string
    {
        $data = array_filter([
            '@context' => 'https://schema.org/',
            '@type' => 'JobPosting',
            'title' => $posting->title,
            'description' => $posting->description ?: e((string) $posting->missions),
            'identifier' => ['@type' => 'PropertyValue', 'name' => $organization->name, 'value' => $posting->reference],
            'datePosted' => $posting->published_at?->toIso8601String(),
            'validThrough' => $posting->closes_at?->toIso8601String(),
            'employmentType' => match ($posting->contract_type) {
                ContractType::Permanent => 'FULL_TIME',
                ContractType::FixedTerm => 'TEMPORARY',
                ContractType::Internship => 'INTERN',
                ContractType::Volunteering => 'VOLUNTEER',
                ContractType::Consultancy, ContractType::Freelance => 'CONTRACTOR',
                ContractType::Other, null => null,
            },
            'hiringOrganization' => array_filter([
                '@type' => 'Organization',
                'name' => $organization->name,
                'sameAs' => $organization->website,
                'logo' => $organization->logo_path ? Storage::disk('public')->url($organization->logo_path) : null,
            ]),
            'jobLocation' => [
                '@type' => 'Place',
                'address' => array_filter([
                    '@type' => 'PostalAddress',
                    'addressLocality' => $posting->location,
                    'addressCountry' => $organization->country,
                ]),
            ],
            'directApply' => true,
            'url' => route('job-postings.show', $posting->slug),
        ], fn ($value) => $value !== null);

        // HEX flags: the JSON is printed inside a <script> element.
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
