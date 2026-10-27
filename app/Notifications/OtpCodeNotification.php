<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent synchronously so codes arrive even without a queue worker. */
class OtpCodeNotification extends Notification
{
    public function __construct(public string $code, public int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Your verification code')
            ->line('Use this code to continue signing in:')
            ->line('**'.$this->code.'**')
            ->line("The code expires in {$this->minutes} minutes. If you did not request it, change your password.");
    }
}
