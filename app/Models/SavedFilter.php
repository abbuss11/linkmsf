<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Saved search of the applications table, personal or shared with the team
 * (§5.2).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $job_posting_id
 * @property int $user_id
 * @property string $name
 * @property array<string, mixed> $filters
 * @property bool $is_shared
 */
#[Fillable(['organization_id', 'job_posting_id', 'user_id', 'name', 'filters', 'is_shared'])]
class SavedFilter extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['filters' => 'array', 'is_shared' => 'boolean'];
    }
}
