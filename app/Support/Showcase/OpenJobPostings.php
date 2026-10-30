<?php

namespace App\Support\Showcase;

use App\Enums\JobPostingStatus;
use App\Enums\OrganizationStatus;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Builder;

/**
 * Offers visible on the public site: published, open now, of an active
 * organisation. Public pages cross organisations on purpose.
 */
class OpenJobPostings
{
    /**
     * @return Builder<JobPosting>
     */
    public static function query(): Builder
    {
        return JobPosting::queryAcrossOrganizations()
            ->where('status', JobPostingStatus::Published->value)
            ->where('closes_at', '>', now())
            ->where(fn (Builder $query) => $query->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->whereHas('organization', fn (Builder $query) => $query->where('status', OrganizationStatus::Active->value));
    }
}
