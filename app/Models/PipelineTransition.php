<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Allowed move from one stage to another; anything else is refused (§5.4).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $pipeline_id
 * @property int $from_stage_id
 * @property int $to_stage_id
 */
#[Fillable(['organization_id', 'pipeline_id', 'from_stage_id', 'to_stage_id'])]
class PipelineTransition extends Model
{
    use BelongsToOrganization;
}
