<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Free label of an organisation, put on applications (§5.1, §5.4).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 */
#[Fillable(['organization_id', 'name'])]
class Tag extends Model
{
    use BelongsToOrganization;
}
