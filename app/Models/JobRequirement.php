<?php

namespace App\Models;

use App\Enums\RequirementUsage;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\JobRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Selection criterion of an offer (§3.1 step 2).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $job_posting_id
 * @property string $label
 * @property string|null $description
 * @property RequirementUsage $usage
 * @property string|null $field_key
 * @property array{operator?: string, value?: mixed, min?: mixed, max?: mixed}|null $rule
 * @property int|null $weight
 * @property int $position
 */
#[Fillable(['organization_id', 'job_posting_id', 'label', 'description', 'usage', 'field_key', 'rule', 'weight', 'position'])]
class JobRequirement extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<JobRequirementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'usage' => RequirementUsage::class,
            'rule' => 'array',
            'weight' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }
}
