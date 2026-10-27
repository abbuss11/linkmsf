<?php

namespace App\Services\AntiSpam;

/**
 * Human verification for public forms. Behind an interface so the provider
 * (Cloudflare Turnstile today) can be replaced.
 */
interface CaptchaVerifier
{
    public function isEnabled(): bool;

    public function verify(?string $token, ?string $ip): bool;
}
