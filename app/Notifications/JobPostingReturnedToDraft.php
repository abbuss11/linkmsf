<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the author of an offer an orgadmin did not approve.
 */
class JobPostingReturnedToDraft extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $reason,
        public readonly string $organizationSlug,
        public readonly string $postingUlid,
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
            ->subject(__('job_postings.notify.returned.subject'))
            ->line(__('job_postings.notify.returned.body', ['title' => $this->title]))
            ->line(__('job_postings.notify.returned.reason', ['reason' => $this->reason]))
            ->action(__('job_postings.notify.open'), url("/app/{$this->organizationSlug}/job-postings/{$this->postingUlid}/edit"));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('job_postings.notify.returned.subject'))
            ->body(__('job_postings.notify.returned.body', ['title' => $this->title]).' '.__('job_postings.notify.returned.reason', ['reason' => $this->reason]))
            ->warning()
            ->getDatabaseMessage();
    }
}
