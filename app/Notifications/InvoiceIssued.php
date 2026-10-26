<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Services\InvoicePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Emailed to a customer (on-demand notifiable) with the PDF attached. */
class InvoiceIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice, public ?string $customMessage = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inv = $this->invoice;
        $company = settings('general.company_name');

        return (new MailMessage)
            ->subject("Invoice {$inv->invoice_number} from {$company}")
            ->greeting('Hello '.$inv->customer_name.',')
            ->line($this->customMessage ?: "Please find attached invoice {$inv->invoice_number}.")
            ->line('Total: '.money($inv->total).' — Balance due: '.money($inv->balance))
            ->line('Due date: '.fmt_date($inv->due_date))
            ->attachData(app(InvoicePdf::class)->render($inv), "{$inv->invoice_number}.pdf", ['mime' => 'application/pdf'])
            ->salutation('Thank you, '.$company);
    }
}
