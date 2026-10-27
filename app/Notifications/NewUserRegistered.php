<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\UsesPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserRegistered extends Notification implements ShouldQueue
{
    use Queueable, UsesPreferences;

    public function __construct(public User $user)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, 'user_registered', 'notifications.admin_approval_email');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New account awaiting approval')
            ->greeting('Hello '.$notifiable->name.',')
            ->line("{$this->user->name} ({$this->user->email}) registered and is waiting for approval.")
            ->action('Review pending accounts', route('users.index', ['status' => 'pending']));
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'New registration', 'message' => "{$this->user->name} is awaiting approval.",
            'url' => route('users.index', ['status' => 'pending']), 'icon' => 'person-plus', 'level' => 'warning'];
    }
}
