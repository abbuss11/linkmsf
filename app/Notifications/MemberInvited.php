<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Sent to the invited email address. Encrypted on the queue because the
 * payload carries the plain invitation token.
 */
class MemberInvited extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Invitation $invitation,
        #[\SensitiveParameter] public readonly string $plainToken,
        public readonly string $organizationName,
        public readonly ?string $inviterName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Signed without expiry: the invitation's own expiry is checked by the
        // application, which shows a clear message instead of a bare 403.
        $url = URL::signedRoute('invitations.show', [
            'invitation' => $this->invitation->ulid,
            'token' => $this->plainToken,
        ]);

        return (new MailMessage)
            ->subject(__('invitations.mail.subject', ['organization' => $this->organizationName]))
            ->greeting(__('invitations.mail.greeting'))
            ->line($this->inviterName
                ? __('invitations.mail.intro_with_inviter', [
                    'inviter' => $this->inviterName,
                    'organization' => $this->organizationName,
                    'role' => $this->invitation->role->getLabel(),
                ])
                : __('invitations.mail.intro', [
                    'organization' => $this->organizationName,
                    'role' => $this->invitation->role->getLabel(),
                ]))
            ->action(__('invitations.mail.action'), $url)
            ->line(__('invitations.mail.expires', [
                'date' => $this->invitation->expires_at->timezone(config('link.display_timezone'))->translatedFormat('j F Y à H:i'),
            ]))
            ->line(__('invitations.mail.ignore'));
    }
}
