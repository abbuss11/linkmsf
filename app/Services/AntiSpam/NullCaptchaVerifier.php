<?php

namespace App\Services\AntiSpam;

/**
 * Used when Turnstile is disabled (local development, tests). The honeypot
 * still applies.
 */
class NullCaptchaVerifier implements CaptchaVerifier
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function verify(?string $token, ?string $ip): bool
    {
        return true;
    }
}
