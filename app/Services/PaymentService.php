<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FinancingRecord;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer payments. Card data is never stored: "reference" holds only a
 * non-sensitive reference (last 4 digits, cheque no., transfer id).
 */
class PaymentService
{
    public function __construct(protected InvoiceService $invoices) {}

    /**
     * @param  array<int,float>  $allocations  invoice_id => amount
     */
    public function record(Customer $customer, array $data, array $allocations): Payment
    {
        $allocations = array_filter(array_map('floatval', $allocations), fn ($a) => $a > 0);
        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }
        if (! $allocations) {
            throw ValidationException::withMessages(['allocations' => 'Allocate the payment to at least one invoice.']);
        }
        if (Money::toCents(Money::sum($allocations)) !== Money::toCents($amount)) {
            throw ValidationException::withMessages(['allocations' => 'Allocated amounts must add up to the payment amount.']);
        }
        if (($data['method'] ?? null) === 'financing') {
            throw ValidationException::withMessages(['method' => 'Lender funds are recorded via the financing settlement on the invoice.']);
        }
        if (in_array($data['method'] ?? null, ['credit_card', 'debit_card'], true)) {
            $this->guardCardData($data['reference'] ?? null);
        }

        return DB::transaction(function () use ($customer, $data, $allocations, $amount) {
            $invoices = Invoice::whereIn('id', array_keys($allocations))->lockForUpdate()->get()->keyBy('id');

            foreach ($allocations as $invoiceId => $alloc) {
                $invoice = $invoices->get($invoiceId);
                if (! $invoice || $invoice->customer_id !== $customer->id) {
                    throw ValidationException::withMessages(['allocations' => 'Invoice does not belong to this customer.']);
                }
                if (in_array($invoice->status, ['draft', 'void'], true)) {
                    throw ValidationException::withMessages(['allocations' => "Invoice {$invoice->invoice_number} is {$invoice->status} and cannot receive payments."]);
                }
                if (Money::toCents($alloc) > Money::toCents($invoice->balance)) {
                    throw ValidationException::withMessages(['allocations' => "Allocation to {$invoice->invoice_number} exceeds its balance of ".money($invoice->balance).'.']);
                }
            }

            $payment = Payment::create([
                'payment_number' => NumberGenerator::next('payment', settings('general.payment_prefix', 'PAY-')),
                'customer_id' => $customer->id,
                'type' => $data['type'] ?? 'payment',
                'payment_date' => $data['payment_date'] ?? today(),
                'amount' => $amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'completed',
                'received_by' => auth()->id(),
            ]);

            foreach ($allocations as $invoiceId => $alloc) {
                $payment->allocations()->create(['invoice_id' => $invoiceId, 'amount' => $alloc]);
                $this->invoices->refreshPaymentState($invoices->get($invoiceId));
            }

            return $payment;
        });
    }

    /** Refund part or all of a payment's allocation to one invoice. */
    public function refund(Payment $payment, Invoice $invoice, float $amount, string $method, ?string $reason = null): Payment
    {
        if ($payment->isRefund() || $payment->status !== 'completed') {
            throw ValidationException::withMessages(['payment' => 'Only completed payments can be refunded.']);
        }
        $allocation = $payment->allocations()->where('invoice_id', $invoice->id)->first();
        if (! $allocation) {
            throw ValidationException::withMessages(['invoice_id' => 'This payment was not applied to that invoice.']);
        }
        $alreadyRefunded = Money::sum($payment->refunds()->completed()->get()
            ->flatMap(fn ($r) => $r->allocations->where('invoice_id', $invoice->id)->pluck('amount')));
        $refundable = Money::sub($allocation->amount, $alreadyRefunded);
        if ($amount <= 0 || Money::toCents($amount) > Money::toCents($refundable)) {
            throw ValidationException::withMessages(['amount' => 'Refund amount must be between 0 and '.money($refundable).'.']);
        }

        return DB::transaction(function () use ($payment, $invoice, $amount, $method, $reason) {
            $refund = Payment::create([
                'payment_number' => NumberGenerator::next('payment', settings('general.payment_prefix', 'PAY-')),
                'customer_id' => $payment->customer_id,
                'type' => 'refund',
                'payment_date' => today(),
                'amount' => $amount,
                'method' => $method,
                'status' => 'completed',
                'refund_of_payment_id' => $payment->id,
                'notes' => $reason,
                'received_by' => auth()->id(),
            ]);
            $refund->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $amount]);
            $this->invoices->refreshPaymentState($invoice->fresh());
            AuditLogger::log('refunded', $invoice, "Refund {$refund->payment_number} of ".money($amount)." on {$invoice->invoice_number}");

            return $refund;
        });
    }

    /** Reverse (cancel) a payment recorded in error. History is kept. */
    public function reverse(Payment $payment, string $reason): Payment
    {
        if ($payment->status === 'reversed') {
            throw ValidationException::withMessages(['payment' => 'Payment already reversed.']);
        }
        if ($payment->refunds()->completed()->exists()) {
            throw ValidationException::withMessages(['payment' => 'Reverse the refunds issued against this payment first.']);
        }

        return DB::transaction(function () use ($payment, $reason) {
            $payment->forceFill([
                'status' => 'reversed', 'reversed_at' => now(), 'reversed_by_user_id' => auth()->id(), 'reversal_reason' => $reason,
            ])->save();

            foreach ($payment->allocations()->with('invoice')->get() as $alloc) {
                if ($alloc->invoice) {
                    $this->invoices->refreshPaymentState($alloc->invoice);
                }
            }
            FinancingRecord::where('settlement_payment_id', $payment->id)
                ->update(['status' => 'approved', 'settled_date' => null, 'settlement_payment_id' => null]);
            AuditLogger::log('reversed', $payment, "Payment {$payment->payment_number} reversed: {$reason}");

            return $payment;
        });
    }

    public function saveFinancing(Invoice $invoice, array $data): FinancingRecord
    {
        if ($invoice->isVoid() || $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => 'Issue the invoice before adding financing.']);
        }
        $existing = $invoice->financing;
        if ($existing && $existing->status === 'settled') {
            throw ValidationException::withMessages(['financing' => 'Financing already settled.']);
        }
        if (Money::toCents($data['financed_amount']) > Money::toCents($invoice->balance)) {
            throw ValidationException::withMessages(['financed_amount' => 'Financed amount cannot exceed the invoice balance ('.money($invoice->balance).').']);
        }

        return DB::transaction(function () use ($invoice, $data, $existing) {
            $record = $existing && $existing->status !== 'cancelled'
                ? tap($existing)->update($data)
                : $invoice->financing()->create($data + ['status' => 'pending']);
            $this->invoices->refreshPaymentState($invoice->fresh());

            return $record;
        });
    }

    /** Lender pays out: only now does the financed amount become cash received. */
    public function settleFinancing(FinancingRecord $financing, string $date, ?string $reference = null): Payment
    {
        if (! in_array($financing->status, ['pending', 'approved'], true)) {
            throw ValidationException::withMessages(['financing' => 'Only pending/approved financing can be settled.']);
        }
        $invoice = $financing->invoice;
        if (Money::toCents($financing->financed_amount) > Money::toCents($invoice->balance)) {
            throw ValidationException::withMessages(['financing' => 'Settlement exceeds the invoice balance.']);
        }

        return DB::transaction(function () use ($financing, $invoice, $date, $reference) {
            $payment = Payment::create([
                'payment_number' => NumberGenerator::next('payment', settings('general.payment_prefix', 'PAY-')),
                'customer_id' => $invoice->customer_id,
                'type' => 'financing_settlement',
                'payment_date' => $date,
                'amount' => $financing->financed_amount,
                'method' => 'financing',
                'reference' => $reference ?: $financing->loan_reference,
                'notes' => "Lender settlement from {$financing->lender_name}",
                'status' => 'completed',
                'received_by' => auth()->id(),
            ]);
            $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => $financing->financed_amount]);
            $financing->update(['status' => 'settled', 'settled_date' => $date, 'settlement_payment_id' => $payment->id]);
            $this->invoices->refreshPaymentState($invoice->fresh());

            return $payment;
        });
    }

    /** Reject anything that looks like a full card number (PCI: never store PAN). */
    protected function guardCardData(?string $reference): void
    {
        if ($reference && preg_match('/\d[\d\s-]{11,}\d/', $reference)) {
            $digits = preg_replace('/\D/', '', $reference);
            if (strlen($digits) >= 13 && strlen($digits) <= 19) {
                throw ValidationException::withMessages(['reference' => 'Do not enter full card numbers. Use only the last 4 digits or a processor transaction ID.']);
            }
        }
    }
}
