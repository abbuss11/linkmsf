<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-organisation, per-year counters (application references).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $year
 * @property int $value
 */
class OrganizationCounter extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'year' => 'integer'];
    }
}
