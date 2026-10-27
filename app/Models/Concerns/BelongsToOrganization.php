<?php

namespace App\Models\Concerns;

use App\Exceptions\MissingOrganizationContextException;
use App\Exceptions\OrganizationReassignmentException;
use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Support\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isolation of organisation data (cahier des charges §2.2).
 *
 * - every query is filtered on the active organisation;
 * - organization_id is filled from the active organisation on creation;
 * - organization_id can never be changed afterwards.
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') !== null) {
                return;
            }

            $organizationId = app(OrganizationContext::class)->id();

            if ($organizationId === null) {
                throw MissingOrganizationContextException::forModel($model::class);
            }

            $model->setAttribute('organization_id', $organizationId);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('organization_id') && $model->getOriginal('organization_id') !== null) {
                throw OrganizationReassignmentException::forModel($model::class);
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Explicit, greppable escape hatch for platform code (superadmin, public
     * links resolved by a secret token, scheduled maintenance).
     *
     * @return Builder<static>
     */
    public static function queryAcrossOrganizations(): Builder
    {
        return static::query()->withoutGlobalScope(OrganizationScope::class);
    }

    /**
     * @return Builder<static>
     */
    public static function queryForOrganization(Organization $organization): Builder
    {
        $query = static::queryAcrossOrganizations();

        return $query->where($query->qualifyColumn('organization_id'), $organization->getKey());
    }
}
