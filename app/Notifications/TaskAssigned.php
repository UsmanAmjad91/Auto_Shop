<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable, UsesPreferences;

    public function __construct(public string $title, public string $message, public string $url) {}

    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, 'job_assigned');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->line($this->message)->action('Open', $this->url);
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'url' => $this->url, 'icon' => 'clipboard-check', 'level' => 'info'];
    }
}
