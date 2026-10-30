<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Encrypted on the queue: the payload carries the reset token.
 */
class ResetCandidatePassword extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('candidate.mail.reset.subject'))
            ->line(__('candidate.mail.reset.intro'))
            ->action(__('candidate.mail.reset.action'), $url)
            ->line(__('candidate.mail.reset.expires', ['count' => config('auth.passwords.users.expire')]))
            ->line(__('candidate.mail.reset.outro'));
    }
}
