<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Unfinished application, 48 hours before the deadline (§4.5).
 */
class DraftDeadlineReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $applicationUlid,
        public readonly string $postingTitle,
        public readonly string $organizationName,
        public readonly string $deadline,
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
        return (new MailMessage)
            ->subject(__('applications.mail.reminder.subject', ['title' => $this->postingTitle]))
            ->line(__('applications.mail.reminder.body', [
                'title' => $this->postingTitle,
                'organization' => $this->organizationName,
                'deadline' => $this->deadline,
            ]))
            ->action(__('applications.mail.reminder.action'), route('candidate.applications.edit', $this->applicationUlid));
    }
}
