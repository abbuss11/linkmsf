<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * MFA is mandatory for superadmins and orgadmins, optional for recruiters and
 * jury members (cahier des charges §10.1).
 */
class EnsurePrivilegedUsersHaveMultiFactorAuthentication extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->requiresMultiFactorAuthentication()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
