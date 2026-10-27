<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\OrganizationRole;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $organization_id
 * @property string $email
 * @property OrganizationRole $role
 * @property string $token_hash
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property int|null $accepted_by
 * @property Carbon|null $revoked_at
 * @property-read InvitationStatus $status
 * @property-read Organization $organization
 */
#[Fillable(['organization_id', 'email', 'role', 'token_hash', 'invited_by', 'expires_at'])]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<InvitationFactory> */
    use HasFactory, HasUlids;

    public const VALIDITY_DAYS = 7;

    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function matchesToken(?string $plainToken): bool
    {
        return is_string($plainToken)
            && $plainToken !== ''
            && hash_equals($this->token_hash, self::hashToken($plainToken));
    }

    public function getStatusAttribute(): InvitationStatus
    {
        return match (true) {
            $this->accepted_at !== null => InvitationStatus::Accepted,
            $this->revoked_at !== null => InvitationStatus::Revoked,
            $this->expires_at->isPast() => InvitationStatus::Expired,
            default => InvitationStatus::Pending,
        };
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending;
    }

    /**
     * @param  Builder<Invitation>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
