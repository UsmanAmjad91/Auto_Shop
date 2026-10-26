<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inv = $this->invoice;

        return (new MailMessage)
            ->subject("Payment reminder: invoice {$inv->invoice_number}")
            ->greeting('Hello '.$inv->customer_name.',')
            ->line("This is a friendly reminder that invoice {$inv->invoice_number} dated ".fmt_date($inv->invoice_date).
                ' has an outstanding balance of '.money($inv->customerBalance()).'.')
            ->line('Due date: '.fmt_date($inv->due_date))
            ->line('If you have already paid, please disregard this message.')
            ->salutation('Regards, '.settings('general.company_name'));
    }
}
