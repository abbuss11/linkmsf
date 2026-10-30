<?php

namespace App\Listeners;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;

/**
 * Audits authentication events. Only the email is kept for failed attempts,
 * never the submitted password. Methods are named on* (not handle*) so that
 * Laravel's listener discovery does not register them a second time.
 */
class RecordAuthenticationEvents
{
    public function __construct(private readonly RecordAuditEvent $audit) {}

    public function onLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->audit->handle(AuditEvent::LoginSucceeded, $event->user, $event->user);
    }

    public function onFailed(Failed $event): void
    {
        $this->audit->handle(
            AuditEvent::LoginFailed,
            properties: ['email' => mb_substr((string) ($event->credentials['email'] ?? ''), 0, 255)],
        );
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->handle(AuditEvent::Logout, $event->user, $event->user);
        }
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->handle(AuditEvent::PasswordReset, $event->user, $event->user);
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Failed::class => 'onFailed',
            Logout::class => 'onLogout',
            PasswordReset::class => 'onPasswordReset',
        ];
    }
}
