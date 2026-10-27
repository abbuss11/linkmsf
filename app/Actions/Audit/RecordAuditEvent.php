<?php

namespace App\Actions\Audit;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes one audit entry. Properties go through a recursive filter so that
 * secrets never reach the audit table, whatever the caller passes (§10.1).
 */
class RecordAuditEvent
{
    /**
     * Keys whose values are always replaced, matched case-insensitively as substrings.
     */
    private const REDACTED_KEYS = [
        'password', 'token', 'secret', 'remember', 'recovery', 'api_key', 'authorization', 'cookie',
    ];

    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        AuditEvent $event,
        ?Model $subject = null,
        ?Model $causer = null,
        Organization|int|null $organization = null,
        array $properties = [],
    ): AuditLog {
        $organizationId = $organization instanceof Organization ? $organization->getKey() : $organization;

        /** @var AuditLog $log */
        $log = activity('audit')
            ->event($event->value)
            ->when($subject !== null, fn ($logger) => $logger->performedOn($subject))
            ->when($causer !== null, fn ($logger) => $logger->causedBy($causer), fn ($logger) => $logger->causedByAnonymous())
            ->withProperties(self::redact($properties))
            ->tap(function (AuditLog $log) use ($organizationId): void {
                $log->organization_id = $organizationId;
                $request = request();
                $log->ip = $request->ip();
                $log->user_agent = mb_substr((string) $request->userAgent(), 0, 512) ?: null;
            })
            ->log($event->value);

        return $log;
    }

    /**
     * @param  array<array-key, mixed>  $properties
     * @return array<array-key, mixed>
     */
    public static function redact(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $properties[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $properties[$key] = self::redact($value);
            }
        }

        return $properties;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::REDACTED_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
