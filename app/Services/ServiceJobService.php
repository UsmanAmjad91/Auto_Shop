<?php

namespace App\Services;

use App\Models\Part;
use App\Models\ServiceJob;
use App\Models\ServiceJobItem;
use App\Models\VehicleStatus;
use App\Support\Money;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceJobService
{
    /** Job status => vehicle repair status (only applied to REPAIR jobs and if the status exists). */
    public const VEHICLE_STATUS_MAP = [
        'scheduled' => 'not_started',
        'in_progress' => 'under_repair',
        'on_hold' => 'waiting_for_parts',
        'completed' => 'repair_completed',
        'quality_checked' => 'ready_for_dispatch',
        'delivered' => 'dispatched',
    ];

    public function __construct(
        protected InventoryService $inventory,
        protected VehicleCostService $vehicleCosts,
    ) {}

    public function create(array $data, array $employees = []): ServiceJob
    {
        return DB::transaction(function () use ($data, $employees) {
            $data['job_number'] = NumberGenerator::next('service_job', settings('general.job_prefix', 'JOB-'));
            $data['created_by'] = auth()->id();
            $data['status'] ??= 'pending';
            $data['checklist'] ??= collect(config('autoshop.service_checklists.'.$data['category'], []))
                ->map(fn ($t) => ['item' => $t, 'done' => false])->all();

            $job = ServiceJob::create($data);
            $this->syncEmployees($job, $employees);

            if ($job->status !== 'pending') {
                $this->applyVehicleStatus($job, null);
            }

            return $job;
        });
    }

    public function update(ServiceJob $job, array $data, ?array $employees = null): ServiceJob
    {
        return DB::transaction(function () use ($job, $data, $employees) {
            $oldStatus = $job->status;
            $newStatus = $data['status'] ?? $oldStatus;
            unset($data['status']);

            $job->fill($data)->save();
            if ($employees !== null) {
                $this->syncEmployees($job, $employees);
            }
            if ($newStatus !== $oldStatus) {
                $this->changeStatus($job, $newStatus);
            }
            $this->recalculate($job);

            return $job;
        });
    }

    public function syncEmployees(ServiceJob $job, array $employees): void
    {
        // [employee_id => ['role' => .., 'hours_worked' => ..]]
        $sync = [];
        foreach ($employees as $id => $pivot) {
            if (is_numeric($pivot)) {
                [$id, $pivot] = [(int) $pivot, []];
            }
            $sync[(int) $id] = ['role' => $pivot['role'] ?? 'technician', 'hours_worked' => (float) ($pivot['hours_worked'] ?? 0)];
        }
        $job->employees()->sync($sync);
    }

    public function changeStatus(ServiceJob $job, string $status, ?string $notes = null): void
    {
        if (! array_key_exists($status, config('autoshop.enums.service_statuses'))) {
            throw ValidationException::withMessages(['status' => 'Invalid status.']);
        }
        if ($job->status === 'cancelled' && $status !== 'cancelled') {
            throw ValidationException::withMessages(['status' => 'Cancelled jobs cannot be re-opened.']);
        }

        DB::transaction(function () use ($job, $status, $notes) {
            $previous = $job->status;
            $attrs = ['status' => $status];
            if ($status === 'in_progress' && ! $job->start_date) {
                $attrs['start_date'] = now();
            }
            if (in_array($status, ['completed', 'quality_checked', 'delivered'], true) && ! $job->completion_date) {
                $attrs['completion_date'] = now();
            }
            $job->forceFill($attrs)->save();

            if ($status === 'cancelled') {
                // Return every issued part to stock.
                foreach ($job->items()->where('item_type', 'part')->get() as $item) {
                    $this->removeItem($job, $item, recalc: false);
                }
            }

            $this->applyVehicleStatus($job, $previous, $notes);
            $this->recalculate($job);
        });
    }

    protected function applyVehicleStatus(ServiceJob $job, ?string $previous, ?string $notes = null): void
    {
        $vehicle = $job->vehicle;
        if (! $vehicle) {
            return;
        }

        if ($job->category === 'repair' && isset(self::VEHICLE_STATUS_MAP[$job->status])) {
            $target = self::VEHICLE_STATUS_MAP[$job->status];
            if (array_key_exists($target, VehicleStatus::options())) {
                $this->vehicleCosts->changeStatus($vehicle, 'repair_status', $target,
                    $notes ?? "Job {$job->job_number}: ".status_label($previous).' → '.status_label($job->status));
            }
        }

        // Company stock is "in repair" while any job is active, and available again when none are.
        if ($vehicle->isCompanyStock() && in_array($vehicle->inventory_status, ['available', 'in_repair'], true)) {
            $active = $vehicle->serviceJobs()->whereIn('status', ['scheduled', 'in_progress', 'on_hold'])->exists();
            $this->vehicleCosts->changeStatus($vehicle, 'inventory_status', $active ? 'in_repair' : 'available',
                "Updated by job {$job->job_number}");
        }
    }

    /** Add a line. PART lines deduct stock atomically (job_issue). */
    public function addItem(ServiceJob $job, array $data, bool $allowNegative = false): ServiceJobItem
    {
        if ($job->isClosed()) {
            throw ValidationException::withMessages(['item' => 'Cannot add items to a closed job.']);
        }
        if ($job->invoice_id && $job->invoice?->status !== 'draft') {
            throw ValidationException::withMessages(['item' => 'This job has already been invoiced.']);
        }

        return DB::transaction(function () use ($job, $data, $allowNegative) {
            $qty = (float) ($data['quantity'] ?? 1);
            if ($qty <= 0) {
                throw ValidationException::withMessages(['quantity' => 'Quantity must be positive.']);
            }

            if ($data['item_type'] === 'part') {
                $part = Part::findOrFail($data['part_id']);
                if ((int) $qty != $qty) {
                    throw ValidationException::withMessages(['quantity' => 'Part quantity must be a whole number.']);
                }
                $data['description'] = ($data['description'] ?? null) ?: $part->name.($part->oem_number ? " ({$part->oem_number})" : '');
                $data['unit_cost'] = $data['unit_cost'] ?? $part->purchase_cost;
                $data['unit_price'] = $data['unit_price'] ?? $part->selling_price;
            }

            $data['quantity'] = $qty;
            $data['unit_cost'] = (float) ($data['unit_cost'] ?? 0);
            $data['unit_price'] = (float) ($data['unit_price'] ?? 0);
            $data['total_cost'] = Money::mul($data['unit_cost'], $qty);
            $data['total_price'] = Money::mul($data['unit_price'], $qty);

            $item = $job->items()->create($data);

            if ($item->item_type === 'part') {
                $this->inventory->move($item->part, 'job_issue', -(int) $qty, [
                    'reference' => $item, 'vehicle_id' => $job->vehicle_id, 'unit_cost' => $item->unit_cost,
                    'notes' => "Issued to job {$job->job_number}", 'allow_negative' => $allowNegative,
                ]);
            }

            $this->recalculate($job);

            return $item;
        });
    }

    public function removeItem(ServiceJob $job, ServiceJobItem $item, bool $recalc = true): void
    {
        if ($item->service_job_id !== $job->id) {
            abort(404);
        }
        if ($job->invoice_id && $job->invoice?->status !== 'draft' && $job->status !== 'cancelled') {
            throw ValidationException::withMessages(['item' => 'This job has already been invoiced.']);
        }

        DB::transaction(function () use ($job, $item, $recalc) {
            if ($item->item_type === 'part' && $item->part) {
                $this->inventory->move($item->part, 'job_return', (int) $item->quantity, [
                    'vehicle_id' => $job->vehicle_id, 'unit_cost' => $item->unit_cost,
                    'notes' => "Returned from job {$job->job_number}",
                ]);
            }
            $item->delete();
            if ($recalc) {
                $this->recalculate($job);
            }
        });
    }

    /** Recompute job totals from its items, then refresh the vehicle's cost columns. */
    public function recalculate(ServiceJob $job): ServiceJob
    {
        $items = $job->items()->get();
        $labor = Money::sum($items->whereIn('item_type', ['labor', 'service'])->pluck('total_cost'));
        $material = Money::sum($items->where('item_type', 'material')->pluck('total_cost'));
        $parts = Money::sum($items->where('item_type', 'part')->pluck('total_cost'));
        $charge = $items->isNotEmpty() ? Money::sum($items->pluck('total_price')) : (float) $job->customer_charge;

        $job->forceFill([
            'labor_cost' => $labor,
            'material_cost' => $material,
            'parts_cost' => $parts,
            'total_cost' => Money::add($labor, $material, $parts),
            'customer_charge' => $charge,
        ])->saveQuietly();

        if ($job->vehicle) {
            $this->vehicleCosts->recalculate($job->vehicle);
        }

        return $job;
    }

    public function delete(ServiceJob $job): void
    {
        if ($job->invoice_id && $job->invoice && ! in_array($job->invoice->status, ['draft', 'void'], true)) {
            throw ValidationException::withMessages(['job' => 'Invoiced jobs cannot be deleted. Void the invoice first.']);
        }
        DB::transaction(function () use ($job) {
            foreach ($job->items as $item) {
                $this->removeItem($job, $item, recalc: false);
            }
            $vehicle = $job->vehicle;
            $job->delete();
            if ($vehicle) {
                $this->vehicleCosts->recalculate($vehicle);
            }
        });
    }
}
