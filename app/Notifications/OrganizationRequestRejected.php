<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationRequestRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $organizationName,
        public readonly string $reason,
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
            ->subject(__('organization_requests.rejected_mail.subject'))
            ->line(__('organization_requests.rejected_mail.intro', ['organization' => $this->organizationName]))
            ->line(__('organization_requests.rejected_mail.reason', ['reason' => $this->reason]))
            ->line(__('organization_requests.rejected_mail.outro'));
    }
}
