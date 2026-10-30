<?php

namespace App\Models;

use App\Enums\EligibilityOutcome;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Result of one eliminatory criterion for one application (§5.3), with the
 * criterion as it was when evaluated.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $application_id
 * @property int|null $job_requirement_id
 * @property string $label
 * @property string|null $field_key
 * @property array<string, mixed>|null $rule
 * @property EligibilityOutcome $outcome
 * @property string|null $value
 * @property Carbon $evaluated_at
 */
#[Fillable(['organization_id', 'application_id', 'job_requirement_id', 'label', 'field_key', 'rule', 'outcome', 'value', 'evaluated_at'])]
class EligibilityResult extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'rule' => 'array',
            'outcome' => EligibilityOutcome::class,
            'evaluated_at' => 'datetime',
        ];
    }
}
