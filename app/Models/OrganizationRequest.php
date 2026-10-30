<?php

namespace App\Models;

use App\Enums\OrganizationRequestStatus;
use Database\Factories\OrganizationRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request for an organisation space, filed from the public site and
 * reviewed by a superadmin. Platform-level: no organisation scope.
 *
 * @property int $id
 * @property string $ulid
 * @property string $organization_name
 * @property string $contact_name
 * @property string $email
 * @property string|null $phone
 * @property string $country
 * @property string|null $message
 * @property OrganizationRequestStatus $status
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property int|null $organization_id
 */
#[Fillable(['organization_name', 'contact_name', 'email', 'phone', 'country', 'message', 'status'])]
class OrganizationRequest extends Model
{
    /** @use HasFactory<OrganizationRequestFactory> */
    use HasFactory, HasUlids;

    protected $attributes = [
        'status' => 'pending',
        'country' => 'NE',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function isPending(): bool
    {
        return $this->status === OrganizationRequestStatus::Pending;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
