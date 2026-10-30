<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $shortlist_id
 * @property int $application_id
 * @property int $position
 * @property string|null $reason
 * @property int|null $added_by
 * @property-read Application $application
 */
#[Fillable(['organization_id', 'shortlist_id', 'application_id', 'position', 'reason', 'added_by'])]
class ShortlistItem extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class)->withoutGlobalScopes();
    }
}
