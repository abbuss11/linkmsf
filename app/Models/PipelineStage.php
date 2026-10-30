<?php

namespace App\Models;

use App\Enums\PipelineStageKind;
use App\Enums\PublicApplicationStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $pipeline_id
 * @property string $key
 * @property string $name
 * @property PipelineStageKind $kind
 * @property PublicApplicationStatus $public_status
 * @property int $position
 * @property bool $is_system
 * @property bool $sends_message
 */
#[Fillable(['organization_id', 'pipeline_id', 'key', 'name', 'kind', 'public_status', 'position', 'is_system', 'sends_message'])]
class PipelineStage extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'kind' => PipelineStageKind::class,
            'public_status' => PublicApplicationStatus::class,
            'position' => 'integer',
            'is_system' => 'boolean',
            'sends_message' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class)->withoutGlobalScopes();
    }

    /**
     * Status shown to the candidate. Decisions stay "under review" until
     * the organisation communicates them (phase 5): a file moved by mistake
     * never reaches the candidate.
     */
    public function visiblePublicStatus(): PublicApplicationStatus
    {
        return match ($this->public_status) {
            PublicApplicationStatus::Selected, PublicApplicationStatus::NotSelected => PublicApplicationStatus::UnderReview,
            default => $this->public_status,
        };
    }
}
