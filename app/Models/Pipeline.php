<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Recruitment pipeline (§5.4): the organisation's template when
 * job_posting_id is null, otherwise the offer's own copy.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $job_posting_id
 */
#[Fillable(['organization_id', 'job_posting_id'])]
class Pipeline extends Model
{
    use BelongsToOrganization;

    /**
     * @return HasMany<PipelineStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->withoutGlobalScopes()->orderBy('position');
    }

    /**
     * @return HasMany<PipelineTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(PipelineTransition::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class)->withoutGlobalScopes();
    }
}
