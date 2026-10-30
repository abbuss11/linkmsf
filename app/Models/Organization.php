<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use Database\Factories\OrganizationFactory;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * The tenant. Not scoped itself: access is decided by memberships.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property OrganizationStatus $status
 * @property string $country
 * @property string $timezone
 * @property string|null $logo_path
 * @property string|null $primary_color
 * @property string|null $secondary_color
 * @property bool $listed_on_job_board
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $approved_at
 */
#[Fillable([
    'name', 'slug', 'status', 'country', 'timezone', 'logo_path',
    'primary_color', 'secondary_color', 'listed_on_job_board', 'settings',
    'approved_at', 'approved_by',
])]
class Organization extends Model implements HasAvatar, HasName
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, SoftDeletes;

    public const DEFAULT_LIMITS = [
        'max_active_job_postings' => 20,
        'storage_quota_mb' => 2048,
    ];

    protected $attributes = [
        'status' => 'pending',
        'country' => 'NE',
        'timezone' => 'Africa/Niamey',
        'listed_on_job_board' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'listed_on_job_board' => 'boolean',
            'settings' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrganizationMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * Active organisation administrators, read across the tenant scope on purpose:
     * this is used by notifications and invariants that run outside a request.
     *
     * @return Collection<int, User>
     */
    public function activeAdmins(): Collection
    {
        return $this->users()
            ->wherePivot('role', OrganizationRole::OrgAdmin->value)
            ->wherePivot('status', MembershipStatus::Active->value)
            ->get();
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }

    /**
     * @return HasMany<JobPosting, $this>
     */
    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    /**
     * Offers must be validated by an orgadmin before publication (§12, off by default).
     */
    public function requiresJobPostingApproval(): bool
    {
        return (bool) data_get($this->settings, 'job_postings.requires_approval', false);
    }

    /**
     * Recruiters see every offer of the organisation (default), or only the
     * offers they are assigned to (§2.1).
     */
    public function recruitersSeeAllJobPostings(): bool
    {
        return (bool) data_get($this->settings, 'job_postings.recruiters_see_all', true);
    }

    public function limit(string $key): int
    {
        return (int) data_get($this->settings, "limits.{$key}", self::DEFAULT_LIMITS[$key] ?? 0);
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
