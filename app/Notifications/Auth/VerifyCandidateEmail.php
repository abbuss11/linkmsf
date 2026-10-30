<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyCandidateEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('candidate.mail.verify.subject'))
            ->line(__('candidate.mail.verify.intro'))
            ->action(__('candidate.mail.verify.action'), $url)
            ->line(__('candidate.mail.verify.outro'));
    }
}
