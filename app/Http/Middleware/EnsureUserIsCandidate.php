<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The candidate space is for candidate accounts only; organisation members
 * are sent to their back-office.
 */
class EnsureUserIsCandidate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isCandidate()) {
            return redirect('/app');
        }

        return $next($request);
    }
}
