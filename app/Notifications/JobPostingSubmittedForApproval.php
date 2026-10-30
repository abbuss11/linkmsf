<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to orgadmins when an offer waits for their validation.
 */
class JobPostingSubmittedForApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $authorName,
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

    private function url(): string
    {
        return url("/app/{$this->organizationSlug}/job-postings/{$this->postingUlid}/edit");
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('job_postings.notify.submitted.subject'))
            ->line(__('job_postings.notify.submitted.body', ['author' => $this->authorName, 'title' => $this->title]))
            ->action(__('job_postings.notify.open'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('job_postings.notify.submitted.subject'))
            ->body(__('job_postings.notify.submitted.body', ['author' => $this->authorName, 'title' => $this->title]))
            ->warning()
            ->getDatabaseMessage();
    }
}
