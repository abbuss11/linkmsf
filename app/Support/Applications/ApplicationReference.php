<?php

namespace App\Support\Applications;

use App\Models\Organization;
use App\Models\OrganizationCounter;

/**
 * Readable application reference, e.g. LNK-2026-SAH-000123 (§4.4). The
 * counter row is locked: must be called inside a transaction.
 */
class ApplicationReference
{
    public static function next(Organization $organization): string
    {
        $year = (int) now()->format('Y');

        OrganizationCounter::queryAcrossOrganizations()->insertOrIgnore([
            'organization_id' => $organization->getKey(),
            'name' => 'application',
            'year' => $year,
            'value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var OrganizationCounter $counter */
        $counter = OrganizationCounter::queryForOrganization($organization)
            ->where('name', 'application')
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $counter->value++;
        $counter->save();

        return sprintf('LNK-%d-%s-%06d', $year, $organization->code(), $counter->value);
    }
}
