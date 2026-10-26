<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $ip, public string $agent, public string $time) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('New sign-in to your account')
            ->line("A sign-in occurred at {$this->time} from IP {$this->ip}.")
            ->line('Device: '.$this->agent)
            ->line('If this was not you, change your password immediately and contact an administrator.')
            ->action('Review sessions', route('profile.security'));
    }
}
