<?php

namespace App\Models;

use App\Enums\NoteVisibility;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal note on an application (§5.5). Never shown to the candidate.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $application_id
 * @property int|null $user_id
 * @property NoteVisibility $visibility
 * @property string $body
 */
#[Fillable(['organization_id', 'application_id', 'user_id', 'visibility', 'body'])]
class Note extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['visibility' => NoteVisibility::class];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
