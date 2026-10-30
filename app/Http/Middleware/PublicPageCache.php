<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Short browser cache for anonymous visitors of the showcase (§4.1), so the
 * back button costs nothing on a slow connection. "private" because Laravel
 * attaches a session cookie to every response: a shared proxy must not
 * store it.
 */
class PublicPageCache
{
    public function handle(Request $request, Closure $next, int $seconds = 60): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $request->user() === null && $response->getStatusCode() === 200) {
            $response->headers->set('Cache-Control', "private, max-age={$seconds}");
        }

        return $response;
    }
}
