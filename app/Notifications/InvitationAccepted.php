<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the organisation's admins when someone joins (§8.1).
 */
class InvitationAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $memberName,
        public readonly string $roleLabel,
        public readonly string $organizationName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('invitations.accepted_mail.subject', ['organization' => $this->organizationName]))
            ->line(__('invitations.accepted_mail.body', [
                'name' => $this->memberName,
                'role' => $this->roleLabel,
                'organization' => $this->organizationName,
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('invitations.accepted_mail.subject', ['organization' => $this->organizationName]))
            ->body(__('invitations.accepted_mail.body', [
                'name' => $this->memberName,
                'role' => $this->roleLabel,
                'organization' => $this->organizationName,
            ]))
            ->success()
            ->getDatabaseMessage();
    }
}
