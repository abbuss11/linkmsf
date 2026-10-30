<?php

namespace App\Support\JobPostings;

use App\Models\JobPosting;
use App\Models\Organization;
use Illuminate\Support\Str;

/**
 * Offer slugs are unique across the platform because the public URL is
 * /offres/{slug} (§4.1): title + organisation, with a numeric suffix if needed.
 */
class JobPostingSlug
{
    public static function generate(string $title, Organization $organization, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug(Str::transliterate($title)), 100, '');
        $base = trim(($base !== '' ? $base.'-' : 'offre-').$organization->slug, '-');
        $base = Str::limit($base, 150, '');

        $slug = $base;
        $suffix = 2;

        while (JobPosting::queryAcrossOrganizations()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
