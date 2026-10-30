<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honeypot for public forms: a hidden field that must stay empty, and an
 * encrypted timestamp proving the form was displayed at least a few seconds
 * before being submitted. Rendered by the <x-honeypot /> component.
 */
class ProtectAgainstSpam
{
    public const TRAP_FIELD = 'website';

    public const TIME_FIELD = '_started_at';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $this->looksLikeSpam($request)) {
            throw ValidationException::withMessages([
                'form' => __('anti_spam.rejected'),
            ]);
        }

        return $next($request);
    }

    private function looksLikeSpam(Request $request): bool
    {
        if (filled($request->input(self::TRAP_FIELD))) {
            return true;
        }

        try {
            $startedAt = (int) Crypt::decryptString((string) $request->input(self::TIME_FIELD));
        } catch (DecryptException) {
            return true;
        }

        return now()->getTimestamp() - $startedAt < (int) config('link.anti_spam.minimum_fill_seconds');
    }
}
