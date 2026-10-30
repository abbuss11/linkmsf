<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirmation e-mail to the candidate, with the reference (§4.4 step 8).
 */
class ApplicationSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $applicationUlid,
        public readonly string $reference,
        public readonly string $postingTitle,
        public readonly string $organizationName,
        public readonly bool $isUpdate,
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
        $key = $this->isUpdate ? 'updated' : 'submitted';

        return (new MailMessage)
            ->subject(__("applications.mail.{$key}.subject", ['reference' => $this->reference]))
            ->line(__("applications.mail.{$key}.body", ['title' => $this->postingTitle, 'organization' => $this->organizationName]))
            ->line(__('applications.mail.reference', ['reference' => $this->reference]))
            ->action(__('applications.mail.action'), route('candidate.applications.show', $this->applicationUlid));
    }
}
