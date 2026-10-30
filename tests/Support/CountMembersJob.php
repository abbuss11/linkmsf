<?php

namespace Tests\Support;

use App\Jobs\OrganizationJob;
use App\Models\Invitation;
use App\Models\OrganizationMembership;
use App\Support\Tenancy\OrganizationContext;

/**
 * Test double: records what an organisation-scoped job can see.
 */
class CountMembersJob extends OrganizationJob
{
    /** @var array<string, mixed> */
    public static array $seen = [];

    public function __construct(Invitation $invitation)
    {
        $this->forOrganizationOf($invitation);
    }

    public function handle(OrganizationContext $context): void
    {
        static::$seen = [
            'organization_id' => $context->id(),
            'members' => OrganizationMembership::query()->count(),
        ];
    }
}
