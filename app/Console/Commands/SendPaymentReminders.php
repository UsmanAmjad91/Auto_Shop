<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Notifications\PaymentReminder;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendPaymentReminders extends Command
{
    protected $signature = 'autoshop:payment-reminders';

    protected $description = 'Email reminders for overdue customer invoices (per Settings > Operations)';

    public function handle(): int
    {
        if (! settings()->bool('reminders.enabled', true)) {
            $this->info('Payment reminders are disabled.');

            return self::SUCCESS;
        }
        $after = (int) settings('reminders.days_after_due', 3);
        $every = (int) settings('reminders.repeat_every_days', 7);
        $n = 0;

        Invoice::open()->whereNotNull('customer_email')->whereDate('due_date', '<=', today()->subDays($after))
            ->where(fn ($q) => $q->whereNull('last_reminder_at')->orWhere('last_reminder_at', '<=', now()->subDays($every)))
            ->with('financing')->each(function (Invoice $inv) use (&$n) {
                if ($inv->customerBalance() <= 0) {
                    return; // only the lender owes money
                }
                Notification::route('mail', $inv->customer_email)->notify(new PaymentReminder($inv));
                $inv->forceFill(['last_reminder_at' => now()])->saveQuietly();
                AuditLogger::log('reminder_sent', $inv, "Automatic payment reminder for {$inv->invoice_number}");
                $n++;
            });

        $this->info("{$n} reminder(s) queued.");

        return self::SUCCESS;
    }
}
