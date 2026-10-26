<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payment->load('allocations.invoice');
        $mail = (new MailMessage)->subject("Payment receipt {$p->payment_number}")
            ->line('Thank you. We received your payment of '.money($p->amount).' on '.fmt_date($p->payment_date).'.')
            ->line('Method: '.config('autoshop.enums.payment_methods.'.$p->method, $p->method));
        foreach ($p->allocations as $a) {
            $mail->line("Applied to {$a->invoice->invoice_number}: ".money($a->amount).' (remaining balance '.money($a->invoice->balance).')');
        }

        return $mail->salutation('Regards, '.settings('general.company_name'));
    }
}
