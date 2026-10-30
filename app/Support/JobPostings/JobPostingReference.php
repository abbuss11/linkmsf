<?php

namespace App\Support\JobPostings;

use App\Models\JobPosting;
use App\Models\Organization;

/**
 * Readable offer reference, e.g. SAH-2026-004: organisation prefix, year and
 * a per-organisation counter. Organisations may overwrite it.
 */
class JobPostingReference
{
    public static function next(Organization $organization, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $prefix = $organization->code();
        $count = JobPosting::queryForOrganization($organization)->withTrashed()->count();

        do {
            $reference = sprintf('%s-%d-%03d', $prefix, $year, ++$count);
        } while (self::exists($organization, $reference));

        return $reference;
    }

    public static function exists(Organization $organization, string $reference, ?int $ignoreId = null): bool
    {
        return JobPosting::queryForOrganization($organization)
            ->withTrashed()
            ->where('reference', $reference)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
