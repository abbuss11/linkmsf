<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Enums\Permission;
use App\Enums\UserType;
use App\Notifications\Auth\ResetCandidatePassword;
use App\Notifications\Auth\VerifyCandidateEmail;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Internal users (superadmin, organisation members) and candidates share this
 * table; `type` keeps them apart (cahier des charges §9.1).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserType $type
 * @property bool $is_superadmin
 * @property Carbon|null $email_verified_at
 * @property string|null $app_authentication_secret
 * @property array<string>|null $app_authentication_recovery_codes
 * @property bool $has_email_authentication
 * @property Carbon|null $last_login_at
 */
#[Fillable(['name', 'email', 'password', 'type'])]
#[Hidden([
    'password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes',
])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasEmailAuthentication, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Per-instance cache of roles by organisation id, to avoid repeated queries
     * from policies during a single request.
     *
     * @var array<int, OrganizationRole|null>
     */
    private array $resolvedRoles = [];

    protected $attributes = [
        'is_superadmin' => false,
        'has_email_authentication' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'is_superadmin' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'has_email_authentication' => 'boolean',
        ];
    }

    public function isCandidate(): bool
    {
        return $this->type === UserType::Candidate;
    }

    public function isInternal(): bool
    {
        return $this->type === UserType::Internal;
    }

    public function isSuperadmin(): bool
    {
        return $this->isInternal() && $this->is_superadmin;
    }

    /**
     * Memberships across every organisation. Read without the tenant scope on
     * purpose: a user's own memberships define which tenants they may enter.
     *
     * @return HasMany<OrganizationMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class)
            ->withoutGlobalScope(Scopes\OrganizationScope::class);
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * Role of the user in the given organisation, or null when the user is not
     * an active member of an active organisation.
     */
    public function roleIn(Organization|int|null $organization): ?OrganizationRole
    {
        if ($organization === null || ! $this->isInternal()) {
            return null;
        }

        $organizationId = $organization instanceof Organization ? $organization->getKey() : $organization;

        if (array_key_exists($organizationId, $this->resolvedRoles)) {
            return $this->resolvedRoles[$organizationId];
        }

        $role = $this->organizations()
            ->whereKey($organizationId)
            ->where('organizations.status', OrganizationStatus::Active->value)
            ->wherePivot('status', MembershipStatus::Active->value)
            ->value('organization_memberships.role');

        return $this->resolvedRoles[$organizationId] = $role !== null ? OrganizationRole::from($role) : null;
    }

    public function hasPermissionIn(Organization|int|null $organization, Permission $permission): bool
    {
        return $this->roleIn($organization)?->can($permission) ?? false;
    }

    public function forgetResolvedRoles(): void
    {
        $this->resolvedRoles = [];
    }

    /**
     * Superadmins and anyone holding an orgadmin role must use MFA (§10.1).
     */
    public function requiresMultiFactorAuthentication(): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->memberships()
            ->where('role', OrganizationRole::OrgAdmin->value)
            ->where('status', MembershipStatus::Active->value)
            ->exists();
    }

    // Filament: panels and tenants

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->isInternal()) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->isSuperadmin(),
            'app' => true,
            default => false,
        };
    }

    /**
     * @return Collection<int, Organization>
     */
    public function getTenants(Panel $panel): Collection
    {
        if (! $this->isInternal()) {
            return new Collection;
        }

        return $this->organizations()
            ->where('organizations.status', OrganizationStatus::Active->value)
            ->wherePivot('status', MembershipStatus::Active->value)
            ->orderBy('organizations.name')
            ->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Organization && $this->roleIn($tenant) !== null;
    }

    // Filament: multi-factor authentication

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  ?array<string>  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function hasEmailAuthentication(): bool
    {
        return $this->has_email_authentication;
    }

    public function toggleEmailAuthentication(bool $condition): void
    {
        $this->has_email_authentication = $condition;
        $this->save();
    }

    // Candidate-side notifications (staff use Filament's own)

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyCandidateEmail);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetCandidatePassword($token));
    }
}
