<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\PayrollService;
use Illuminate\Console\Command;

class RefreshStatuses extends Command
{
    protected $signature = 'autoshop:refresh-statuses';

    protected $description = 'Mark overdue invoices and payroll entries';

    public function handle(InvoiceService $invoices, PayrollService $payroll): int
    {
        $n = 0;
        Invoice::whereIn('status', ['issued', 'partially_paid', 'overdue'])->each(function ($inv) use ($invoices, &$n) {
            $before = $inv->status;
            $invoices->refreshPaymentState($inv);
            $n += $before !== $inv->status ? 1 : 0;
        });
        $p = $payroll->markOverdue();
        $this->info("{$n} invoice(s) and {$p} payroll entr(ies) changed status.");

        return self::SUCCESS;
    }
}
