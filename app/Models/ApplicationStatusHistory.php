<?php

namespace App\Models;

use App\Enums\HistoryOrigin;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One stage change (§5.4): never modified nor deleted.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $application_id
 * @property int|null $from_stage_id
 * @property int|null $to_stage_id
 * @property string|null $from_stage_name
 * @property string $to_stage_name
 * @property int|null $user_id
 * @property HistoryOrigin $origin
 * @property string|null $reason
 * @property string|null $comment
 * @property Carbon $created_at
 */
#[Fillable(['organization_id', 'application_id', 'from_stage_id', 'to_stage_id', 'from_stage_name', 'to_stage_name', 'user_id', 'origin', 'reason', 'comment'])]
class ApplicationStatusHistory extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'origin' => HistoryOrigin::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
