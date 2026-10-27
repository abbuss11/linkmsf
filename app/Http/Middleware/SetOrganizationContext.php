<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Tenancy\OrganizationContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after Filament has identified the tenant from the URL and checked
 * that the user may access it (canAccessTenant), on every page and Livewire
 * request of the /app panel.
 */
class SetOrganizationContext
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Organization) {
            $this->context->set($tenant);
        }

        return $next($request);
    }
}
