<?php

namespace App\Notifications;

use App\Models\Part;
use App\Notifications\Concerns\UsesPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable, UsesPreferences;

    public function __construct(public Part $part) {}

    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable, 'low_stock', 'notifications.low_stock_email');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("Low stock: {$this->part->name}")
            ->line("{$this->part->name} ({$this->part->part_number}) is ".status_label($this->part->stock_status).'.')
            ->line("Available: {$this->part->quantity_available} — Minimum level: {$this->part->min_stock_level}")
            ->action('View part', route('parts.show', $this->part));
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Low stock', 'message' => "{$this->part->name}: {$this->part->quantity_available} available (min {$this->part->min_stock_level}).",
            'url' => route('parts.show', $this->part), 'icon' => 'box-seam', 'level' => 'danger'];
    }
}
