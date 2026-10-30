<?php

namespace App\Models;

use App\Enums\ShortlistKind;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Long list, shortlist or waiting list of an offer (§5.6).
 *
 * @property int $id
 * @property string $ulid
 * @property int $organization_id
 * @property int $job_posting_id
 * @property string $name
 * @property ShortlistKind $kind
 * @property int|null $created_by
 */
#[Fillable(['organization_id', 'job_posting_id', 'name', 'kind', 'created_by'])]
class Shortlist extends Model
{
    use BelongsToOrganization, HasUlids;

    protected function casts(): array
    {
        return ['kind' => ShortlistKind::class];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<ShortlistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShortlistItem::class)->withoutGlobalScopes()->orderBy('position');
    }
}
