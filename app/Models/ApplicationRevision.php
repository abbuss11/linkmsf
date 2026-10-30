<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Previous content of an application modified after submission (§4.5).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $application_id
 * @property array<string, mixed> $snapshot
 */
class ApplicationRevision extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'submitted_at' => 'datetime'];
    }
}
