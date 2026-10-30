<?php

namespace App\Notifications;

use App\Models\OrganizationRequest;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to superadmins when an organisation asks for a space (§8.1).
 */
class NewOrganizationRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly OrganizationRequest $request) {}

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
            ->subject(__('organization_requests.notify.subject'))
            ->line(__('organization_requests.notify.body', [
                'organization' => $this->request->organization_name,
                'contact' => $this->request->contact_name,
            ]))
            ->action(__('organization_requests.notify.action'), url('/admin/organization-requests'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('organization_requests.notify.subject'))
            ->body(__('organization_requests.notify.body', [
                'organization' => $this->request->organization_name,
                'contact' => $this->request->contact_name,
            ]))
            ->info()
            ->getDatabaseMessage();
    }
}
