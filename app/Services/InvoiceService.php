<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\ServiceJob;
use App\Models\Vehicle;
use App\Support\Money;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invoice lifecycle. Every operation that touches stock, vehicles or money runs in
 * a single DB transaction so the invoice, inventory and vehicle never disagree.
 *
 *  draft  --issue()-->  issued/partially_paid/paid/overdue  --void()--> void
 *  Side effects of issuing (reversed on void / re-edit):
 *   - PART lines deduct stock (type "sale")
 *   - VEHICLE lines mark the vehicle sold and record sale price / buyer
 *   - SERVICE_JOB lines link the job to the invoice (job parts were already issued)
 */
class InvoiceService
{
    public function __construct(
        protected InventoryService $inventory,
        protected VehicleCostService $vehicleCosts,
        protected ServiceJobService $jobs,
    ) {}

    public function create(array $data, array $items, bool $issue = false): Invoice
    {
        return DB::transaction(function () use ($data, $items, $issue) {
            $invoice = new Invoice;
            $invoice->invoice_number = NumberGenerator::next('invoice', settings('general.invoice_prefix', 'INV-'));
            $invoice->status = 'draft';
            $invoice->created_by = auth()->id();
            $this->fill($invoice, $data);
            $invoice->save();

            $this->syncItems($invoice, $items);
            $this->recalculateTotals($invoice);

            if ($issue) {
                $this->issue($invoice);
            }

            return $invoice->fresh(['items']);
        });
    }

    public function update(Invoice $invoice, array $data, array $items): Invoice
    {
        if ($invoice->isVoid()) {
            throw ValidationException::withMessages(['invoice' => 'Void invoices cannot be edited.']);
        }
        if (! $invoice->isDraft() && $invoice->allocations()->exists()) {
            throw ValidationException::withMessages(['invoice' => 'Invoices with recorded payments cannot be edited. Reverse or refund the payments first, or issue a credit/new invoice.']);
        }

        return DB::transaction(function () use ($invoice, $data, $items) {
            $wasIssued = ! $invoice->isDraft();
            if ($wasIssued) {
                $this->revertSideEffects($invoice);
            }

            $this->fill($invoice, $data);
            $invoice->save();
            $this->syncItems($invoice, $items);
            $this->recalculateTotals($invoice);

            if ($wasIssued) {
                $invoice->status = 'draft';
                $this->issue($invoice);
            }

            return $invoice->fresh(['items']);
        });
    }

    protected function fill(Invoice $invoice, array $data): void
    {
        $customer = Customer::findOrFail($data['customer_id']);
        $invoice->fill([
            'type' => $data['type'],
            'customer_id' => $customer->id,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'sales_rep_id' => $data['sales_rep_id'] ?? auth()->id(),
            'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
            'due_date' => $data['due_date'] ?? now()->addDays((int) settings('general.invoice_due_days', 15))->toDateString(),
            'discount_type' => $data['discount_type'] ?? 'fixed',
            'discount_value' => $data['discount_value'] ?? 0,
            'tax_rate' => $data['tax_rate'] ?? settings('general.tax_rate', 0),
            'notes' => $data['notes'] ?? null,
            'terms' => $data['terms'] ?? settings('general.invoice_terms'),
            // snapshot
            'customer_name' => $customer->display_name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'customer_address' => $customer->fullAddress(),
        ]);
    }

    /** Replace all lines. Costs are captured from source records for margin reporting. */
    protected function syncItems(Invoice $invoice, array $items): void
    {
        if (count($items) === 0) {
            throw ValidationException::withMessages(['items' => 'Add at least one line item.']);
        }

        $invoice->items()->delete();
        foreach (array_values($items) as $i => $row) {
            $type = $row['item_type'];
            $qty = (float) ($row['quantity'] ?? 1);
            $price = (float) ($row['unit_price'] ?? 0);
            $cost = (float) ($row['unit_cost'] ?? 0);
            $description = trim((string) ($row['description'] ?? ''));

            if ($type === 'vehicle') {
                $vehicle = Vehicle::findOrFail($row['vehicle_id']);
                $qty = 1;
                $cost = (float) $vehicle->total_investment;
                $description = $description ?: "{$vehicle->title()} — VIN {$vehicle->vin} (Stock {$vehicle->stock_number})";
            } elseif ($type === 'part') {
                $part = Part::findOrFail($row['part_id']);
                if ((int) $qty != $qty || $qty <= 0) {
                    throw ValidationException::withMessages(["items.$i.quantity" => 'Part quantity must be a positive whole number.']);
                }
                $cost = (float) $part->purchase_cost;
                $description = $description ?: $part->name.($part->oem_number ? " ({$part->oem_number})" : '');
            } elseif ($type === 'service_job') {
                $job = ServiceJob::findOrFail($row['service_job_id']);
                if ($job->invoice_id && $job->invoice_id !== $invoice->id && $job->invoice?->status !== 'void') {
                    throw ValidationException::withMessages(["items.$i.service_job_id" => "Job {$job->job_number} is already on invoice #{$job->invoice?->invoice_number}."]);
                }
                $qty = 1;
                $cost = (float) $job->total_cost;
                $price = $price ?: (float) $job->customer_charge;
                $description = $description ?: "{$job->categoryLabel()} — {$job->job_number}: {$job->title}";
            }

            if ($qty <= 0) {
                throw ValidationException::withMessages(["items.$i.quantity" => 'Quantity must be positive.']);
            }

            $invoice->items()->create([
                'item_type' => $type,
                'vehicle_id' => $type === 'vehicle' ? $row['vehicle_id'] : null,
                'part_id' => $type === 'part' ? $row['part_id'] : null,
                'service_job_id' => $type === 'service_job' ? $row['service_job_id'] : null,
                'description' => $description ?: config('autoshop.enums.invoice_item_types.'.$type, 'Item'),
                'quantity' => $qty,
                'unit_price' => $price,
                'unit_cost' => $cost,
                'taxable' => filter_var($row['taxable'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'line_total' => Money::mul($price, $qty),
                'sort_order' => $i,
            ]);
        }
    }

    public function recalculateTotals(Invoice $invoice): Invoice
    {
        $items = $invoice->items()->get();
        $subtotal = Money::sum($items->pluck('line_total'));
        $taxableBase = Money::sum($items->where('taxable', true)->pluck('line_total'));

        $discount = $invoice->discount_type === 'percent'
            ? Money::percent($subtotal, min(100, (float) $invoice->discount_value))
            : min((float) $invoice->discount_value, $subtotal);

        // Discount is spread proportionally across taxable/non-taxable lines.
        $taxableAfterDiscount = $subtotal > 0 ? $taxableBase - ($discount * ($taxableBase / $subtotal)) : 0;
        $tax = Money::percent(max(0, $taxableAfterDiscount), (float) $invoice->tax_rate);
        $total = Money::add($subtotal, -$discount, $tax);

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => $total,
            'cost_total' => Money::sum($items->map(fn ($i) => Money::mul($i->unit_cost, $i->quantity))),
        ])->saveQuietly();

        return $this->refreshPaymentState($invoice);
    }

    /** Finalise a draft: apply stock + vehicle side-effects atomically. */
    public function issue(Invoice $invoice, bool $allowNegative = false): Invoice
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => 'Only draft invoices can be issued.']);
        }

        return DB::transaction(function () use ($invoice, $allowNegative) {
            foreach ($invoice->items()->get() as $item) {
                if ($item->item_type === 'part' && ! $item->stock_deducted) {
                    $this->inventory->move($item->part, 'sale', -(int) $item->quantity, [
                        'reference' => $item, 'vehicle_id' => $invoice->vehicle_id, 'unit_cost' => $item->unit_cost,
                        'notes' => "Sold on invoice {$invoice->invoice_number}", 'allow_negative' => $allowNegative,
                    ]);
                    $item->forceFill(['stock_deducted' => true])->saveQuietly();
                }

                if ($item->item_type === 'vehicle') {
                    $vehicle = Vehicle::whereKey($item->vehicle_id)->lockForUpdate()->firstOrFail();
                    if (! $vehicle->isCompanyStock()) {
                        throw ValidationException::withMessages(['items' => "Vehicle {$vehicle->stock_number} is a customer vehicle and cannot be sold."]);
                    }
                    if ($vehicle->sale_invoice_id && $vehicle->sale_invoice_id !== $invoice->id) {
                        throw ValidationException::withMessages(['items' => "Vehicle {$vehicle->stock_number} has already been sold."]);
                    }
                    $vehicle->forceFill([
                        'sale_price' => $item->line_total,
                        'sold_at' => $invoice->invoice_date,
                        'sold_to_customer_id' => $invoice->customer_id,
                        'sale_invoice_id' => $invoice->id,
                    ])->saveQuietly();
                    $this->vehicleCosts->changeStatus($vehicle, 'inventory_status', 'sold', "Sold on invoice {$invoice->invoice_number}");
                }

                if ($item->item_type === 'service_job') {
                    ServiceJob::whereKey($item->service_job_id)->update(['invoice_id' => $invoice->id]);
                }
            }

            $invoice->forceFill(['status' => 'issued', 'issued_at' => $invoice->issued_at ?? now()])->saveQuietly();
            AuditLogger::log('issued', $invoice, "Invoice {$invoice->invoice_number} issued");

            return $this->refreshPaymentState($invoice);
        });
    }

    public function void(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->isVoid()) {
            return $invoice;
        }
        $invoice->refresh();
        if ((float) $invoice->amount_paid > 0) {
            throw ValidationException::withMessages(['invoice' => 'Refund or reverse all payments before voiding this invoice.']);
        }

        return DB::transaction(function () use ($invoice, $reason) {
            if (! $invoice->isDraft()) {
                $this->revertSideEffects($invoice);
            }
            $invoice->financing?->update(['status' => 'cancelled']);
            $invoice->forceFill(['status' => 'void', 'payment_status' => 'unpaid', 'voided_at' => now(), 'void_reason' => $reason])->saveQuietly();
            AuditLogger::log('voided', $invoice, "Invoice {$invoice->invoice_number} voided: {$reason}");

            return $invoice;
        });
    }

    /** Undo stock deductions, vehicle sale and job links created by issue(). */
    protected function revertSideEffects(Invoice $invoice): void
    {
        foreach ($invoice->items()->get() as $item) {
            if ($item->item_type === 'part' && $item->stock_deducted && $item->part) {
                $this->inventory->move($item->part, 'sale_return', (int) $item->quantity, [
                    'reference' => $item, 'unit_cost' => $item->unit_cost,
                    'notes' => "Reversed from invoice {$invoice->invoice_number}",
                ]);
                $item->forceFill(['stock_deducted' => false])->saveQuietly();
            }
            if ($item->item_type === 'vehicle' && $item->vehicle && $item->vehicle->sale_invoice_id === $invoice->id) {
                $vehicle = $item->vehicle;
                $vehicle->forceFill(['sale_price' => null, 'sold_at' => null, 'sold_to_customer_id' => null, 'sale_invoice_id' => null])->saveQuietly();
                $this->vehicleCosts->changeStatus($vehicle, 'inventory_status', 'available', "Sale reversed (invoice {$invoice->invoice_number})");
                $this->vehicleCosts->syncPayment($vehicle);
            }
            if ($item->item_type === 'service_job') {
                ServiceJob::whereKey($item->service_job_id)->where('invoice_id', $invoice->id)->update(['invoice_id' => null]);
            }
        }
    }

    /** Recompute amount paid, balance and statuses from completed payment allocations. */
    public function refreshPaymentState(Invoice $invoice): Invoice
    {
        $allocations = $invoice->allocations()->with('payment')->get()
            ->filter(fn ($a) => $a->payment && $a->payment->status === 'completed');

        $paid = Money::sum($allocations->map(fn ($a) => $a->payment->isRefund() ? -1 * (float) $a->amount : (float) $a->amount));
        $hadRefund = $allocations->contains(fn ($a) => $a->payment->isRefund());
        $balance = Money::sub($invoice->total, $paid);
        $invoice->setRelation('financing', $invoice->financing()->first());
        $pendingFinance = $invoice->pendingFinancedAmount();

        $paymentStatus = match (true) {
            $hadRefund && $paid <= 0 => 'refunded',
            $balance <= 0 && (float) $invoice->total > 0 => 'paid',
            $pendingFinance > 0 && Money::sub($balance, $pendingFinance) <= 0 => 'pending',
            $paid > 0 => 'partial',
            default => 'unpaid',
        };

        $status = $invoice->status;
        if (! in_array($status, ['draft', 'void'], true)) {
            $status = match ($paymentStatus) {
                'paid' => 'paid',
                'refunded' => 'refunded',
                'partial' => 'partially_paid',
                default => 'issued',
            };
            if (in_array($status, ['issued', 'partially_paid'], true) && $invoice->due_date && $invoice->due_date->lt(today()) && $balance > 0) {
                $status = 'overdue';
            }
        }

        $invoice->forceFill([
            'amount_paid' => $paid,
            'balance' => $balance,
            'payment_status' => $paymentStatus,
            'status' => $status,
        ])->saveQuietly();

        if ($invoice->vehicle_id && ! $invoice->isDraft() && $invoice->vehicle) {
            $this->vehicleCosts->syncPayment($invoice->vehicle);
        }

        return $invoice;
    }

    /** Build line items for a service job (used by "Create invoice from job"). */
    public function itemsFromJob(ServiceJob $job, bool $itemised = true): array
    {
        if (! $itemised) {
            return [['item_type' => 'service_job', 'service_job_id' => $job->id, 'quantity' => 1, 'unit_price' => $job->customer_charge]];
        }

        return [['item_type' => 'service_job', 'service_job_id' => $job->id, 'quantity' => 1,
            'unit_price' => $job->customer_charge,
            'description' => $job->categoryLabel().' — '.$job->job_number.': '.$job->title.' ('.
                $job->items->map(fn ($i) => $i->description.' ×'.rtrim(rtrim((string) $i->quantity, '0'), '.'))->implode(', ').')']];
    }

    public static function typeForJob(ServiceJob $job): string
    {
        return match ($job->category) {
            'interior' => 'interior',
            'cleaning' => 'detailing',
            default => 'repair',
        };
    }
}
