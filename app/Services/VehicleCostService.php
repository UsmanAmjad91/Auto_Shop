<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\ServiceJobItem;
use App\Models\Vehicle;
use App\Models\VehicleStatusHistory;
use App\Support\Money;

/**
 * Derives a vehicle's cost & payment columns from related records.
 *
 * Cost sources are mutually exclusive, so nothing is double-counted:
 *  - parts_cost      = cost of PART items on all non-cancelled service jobs
 *  - labor_cost      = non-part item costs on REPAIR jobs
 *  - interior_cost   = non-part item costs on INTERIOR jobs
 *  - detailing_cost  = non-part item costs on CLEANING jobs
 *  - other_cost      = manual vehicle_expenses (transport, registration ...)
 *  total_investment  = purchase_price + all of the above
 */
class VehicleCostService
{
    public function recalculate(Vehicle $vehicle): Vehicle
    {
        $items = ServiceJobItem::query()
            ->join('service_jobs', 'service_jobs.id', '=', 'service_job_items.service_job_id')
            ->where('service_jobs.vehicle_id', $vehicle->id)
            ->whereNull('service_jobs.deleted_at')
            ->where('service_jobs.status', '!=', 'cancelled')
            ->get(['service_job_items.item_type', 'service_job_items.total_cost', 'service_jobs.category']);

        $parts = Money::sum($items->where('item_type', 'part')->pluck('total_cost'));
        $nonPart = $items->where('item_type', '!=', 'part');
        $labor = Money::sum($nonPart->where('category', 'repair')->pluck('total_cost'));
        $interior = Money::sum($nonPart->where('category', 'interior')->pluck('total_cost'));
        $detailing = Money::sum($nonPart->where('category', 'cleaning')->pluck('total_cost'));
        $other = Money::sum($vehicle->expenses()->pluck('amount'));

        $vehicle->forceFill([
            'parts_cost' => $parts,
            'labor_cost' => $labor,
            'interior_cost' => $interior,
            'detailing_cost' => $detailing,
            'other_cost' => $other,
            'total_investment' => Money::add($vehicle->purchase_price, $parts, $labor, $interior, $detailing, $other),
        ])->saveQuietly();

        return $vehicle;
    }

    /** Sync amount paid / balance / payment status from invoices for this vehicle. */
    public function syncPayment(Vehicle $vehicle): Vehicle
    {
        $invoices = $vehicle->sale_invoice_id
            ? Invoice::whereKey($vehicle->sale_invoice_id)->with('financing')->get()
            : Invoice::where('vehicle_id', $vehicle->id)->whereNotIn('status', ['draft', 'void'])->with('financing')->get();

        if ($invoices->isEmpty()) {
            $vehicle->forceFill(['amount_paid' => 0, 'balance_due' => 0,
                'payment_status' => $vehicle->payment_status === 'refunded' ? 'refunded' : 'unpaid'])->saveQuietly();

            return $vehicle;
        }

        $paid = Money::sum($invoices->pluck('amount_paid'));
        $balance = Money::sum($invoices->pluck('balance'));
        $statuses = $invoices->pluck('payment_status')->unique();

        $status = match (true) {
            $statuses->count() === 1 => $statuses->first(),
            $balance <= 0 => 'paid',
            $paid > 0 => 'partial',
            default => 'unpaid',
        };

        $vehicle->forceFill(['amount_paid' => $paid, 'balance_due' => $balance, 'payment_status' => $status])->saveQuietly();

        return $vehicle;
    }

    /** Change a status field and record history (repair_status or inventory_status). */
    public function changeStatus(Vehicle $vehicle, string $field, string $to, ?string $notes = null, ?int $employeeId = null): void
    {
        $from = $vehicle->{$field};
        if ($from === $to) {
            return;
        }
        $vehicle->forceFill([$field => $to])->save();

        VehicleStatusHistory::create([
            'vehicle_id' => $vehicle->id,
            'field' => $field,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'employee_id' => $employeeId ?? auth()->user()?->employee?->id,
            'changed_by' => auth()->id(),
        ]);
    }
}
