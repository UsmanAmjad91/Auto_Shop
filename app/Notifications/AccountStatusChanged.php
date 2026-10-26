<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $status, public ?string $notes = null)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Your account has been '.$this->status)->greeting('Hello '.$notifiable->name.',');

        return match ($this->status) {
            'approved' => $mail->line('Good news — your account has been approved. You can now sign in.')->action('Sign in', route('login')),
            'rejected' => $mail->line('Unfortunately your registration was not approved.')->line($this->notes ?? ''),
            'suspended' => $mail->line('Your account has been suspended. Contact an administrator for details.')->line($this->notes ?? ''),
            default => $mail->line('Your account status is now: '.$this->status.'.'),
        };
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Account '.$this->status, 'message' => 'Your account status changed to '.$this->status.'.', 'icon' => 'person-check'];
    }
}
