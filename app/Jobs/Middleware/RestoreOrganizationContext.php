<?php

namespace App\Jobs\Middleware;

use App\Jobs\OrganizationJob;
use App\Models\Organization;
use App\Support\Tenancy\OrganizationContext;
use Closure;
use RuntimeException;

class RestoreOrganizationContext
{
    public function handle(object $job, Closure $next): mixed
    {
        $organizationId = $job instanceof OrganizationJob ? $job->organizationId : null;

        if ($organizationId === null) {
            throw new RuntimeException('Job '.$job::class.' was dispatched without an organization.');
        }

        $organization = Organization::query()->findOrFail($organizationId);

        return app(OrganizationContext::class)->runFor($organization, fn () => $next($job));
    }
}
