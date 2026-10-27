<?php

namespace App\Services\AntiSpam;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileCaptchaVerifier implements CaptchaVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        #[\SensitiveParameter] private readonly string $secretKey,
        private readonly int $timeoutSeconds = 5,
    ) {}

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Fails closed: if Cloudflare cannot be reached, the submission is refused
     * and the user is asked to try again.
     */
    public function verify(?string $token, ?string $ip): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout($this->timeoutSeconds)
                ->post(self::ENDPOINT, array_filter([
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (ConnectionException) {
            Log::warning('Turnstile verification unreachable.');

            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
