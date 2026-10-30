<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recruiter assigned to an offer: used when the organisation restricts
 * recruiters to the offers they are assigned to.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $job_posting_id
 * @property int $user_id
 */
#[Fillable(['organization_id', 'job_posting_id', 'user_id', 'assigned_by'])]
class JobPostingAssignment extends Model
{
    use BelongsToOrganization;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }
}
