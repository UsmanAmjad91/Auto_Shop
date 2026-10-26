<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ServiceJob;
use App\Models\ServiceJobImage;
use App\Models\ServiceJobItem;
use App\Models\ServiceType;
use App\Models\Vehicle;
use App\Notifications\TaskAssigned;
use App\Services\ServiceJobService;
use App\Support\DataTable;
use App\Support\Exporter;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * One module for Repair Jobs, Interior Services and Washing & Detailing
 * (category = repair | interior | cleaning).
 */
class ServiceJobController extends Controller
{
    public function __construct(protected ServiceJobService $jobs) {}

    protected function table(Request $request): DataTable
    {
        return DataTable::make(ServiceJob::visibleTo($request->user())->with(['vehicle', 'customer', 'supervisor', 'employees']), $request)
            ->searchable(['job_number', 'title', 'description', 'vehicle.vin', 'vehicle.stock_number', 'vehicle.make', 'vehicle.model'])
            ->sortable(['job_number' => 'job_number', 'created_at' => 'created_at', 'scheduled_date' => 'scheduled_date',
                'status' => 'status', 'customer_charge' => 'customer_charge', 'total_cost' => 'total_cost'], 'created_at')
            ->filters(fn ($q, $r) => $q->when($r->category, fn ($x) => $x->where('category', $r->category))
                ->when($r->status, fn ($x) => $x->where('status', $r->status))
                ->when($r->priority, fn ($x) => $x->where('priority', $r->priority))
                ->when($r->vehicle_id, fn ($x) => $x->where('vehicle_id', $r->vehicle_id))
                ->when($r->customer_id, fn ($x) => $x->where('customer_id', $r->customer_id))
                ->when($r->employee_id, fn ($x) => $x->where(fn ($w) => $w->where('supervisor_id', $r->employee_id)
                    ->orWhereHas('employees', fn ($e) => $e->where('employees.id', $r->employee_id))))
                ->when($r->service_type_id, fn ($x) => $x->whereHas('items', fn ($i) => $i->where('service_type_id', $r->service_type_id)))
                ->when($r->boolean('unbilled'), fn ($x) => $x->whereNull('invoice_id')->where('is_billable', true)->where('status', '!=', 'cancelled'))
                ->when($r->date_from, fn ($x) => $x->whereDate('created_at', '>=', $r->date_from))
                ->when($r->date_to, fn ($x) => $x->whereDate('created_at', '<=', $r->date_to)));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $request->expectsJson()) {
            $category = in_array($request->category, ['repair', 'interior', 'cleaning'], true) ? $request->category : null;

            return view('service-jobs.index', ['category' => $category,
                'serviceTypes' => ServiceType::when($category, fn ($q) => $q->where('category', $category))->orderBy('name')->pluck('name', 'id')]);
        }
        $fin = $user->can('financials.view') || $user->can('invoices.view');

        return $this->table($request)->transform(fn (ServiceJob $j) => [
            'id' => $j->id, 'url' => route('service-jobs.show', $j), 'job_number' => $j->job_number, 'category' => $j->categoryLabel(),
            'title' => $j->title, 'vehicle' => $j->vehicle?->title(), 'vin' => $j->vehicle?->vin, 'customer' => $j->customer?->display_name,
            'employees' => $j->employees->pluck('full_name')->implode(', '), 'supervisor' => $j->supervisor?->full_name,
            'scheduled' => fmt_date($j->scheduled_date), 'created_at' => fmt_date($j->created_at),
            'total_cost' => $fin ? (float) $j->total_cost : null, 'customer_charge' => $fin ? (float) $j->customer_charge : null,
            'status' => ['label' => status_label($j->status), 'color' => status_color($j->status)],
            'priority' => ['label' => ucfirst($j->priority), 'color' => $j->priority === 'urgent' ? 'danger' : ($j->priority === 'high' ? 'warning' : 'secondary')],
            'invoiced' => $j->invoice_id ? 'Yes' : 'No',
            'actions' => array_values(array_filter([
                ['label' => 'Open', 'icon' => 'eye', 'url' => route('service-jobs.show', $j)],
                $user->can('update', $j) && ! $j->isClosed() ? ['label' => 'Edit', 'icon' => 'pencil', 'url' => route('service-jobs.edit', $j)] : null,
                ['label' => 'Job card', 'icon' => 'printer', 'url' => route('service-jobs.print', $j), 'target' => '_blank'],
            ])),
        ])->response();
    }

    public function export(Request $request)
    {
        $rows = $this->table($request)->all()->map(fn ($j) => ['job' => $j->job_number, 'category' => $j->categoryLabel(), 'title' => $j->title,
            'vehicle' => $j->vehicle?->title(), 'vin' => $j->vehicle?->vin, 'customer' => $j->customer?->display_name, 'status' => $j->status,
            'employees' => $j->employees->pluck('full_name')->implode(', '), 'start' => fmt_date($j->start_date), 'completed' => fmt_date($j->completion_date),
            'labor' => $j->labor_cost, 'material' => $j->material_cost, 'parts' => $j->parts_cost, 'total' => $j->total_cost, 'charge' => $j->customer_charge]);

        return Exporter::download($request->input('format', 'csv'), 'Service Jobs', ['job' => 'Job #', 'category' => 'Category', 'title' => 'Title',
            'vehicle' => 'Vehicle', 'vin' => 'VIN', 'customer' => 'Customer', 'status' => 'Status', 'employees' => 'Employees', 'start' => 'Start',
            'completed' => 'Completed', 'labor' => 'Labor Cost', 'material' => 'Material Cost', 'parts' => 'Parts Cost', 'total' => 'Total Cost',
            'charge' => 'Customer Charge'], $rows);
    }

    public function create(Request $request)
    {
        $vehicle = $request->vehicle_id ? Vehicle::find($request->vehicle_id) : null;
        $job = new ServiceJob([
            'category' => in_array($request->category, ['repair', 'interior', 'cleaning'], true) ? $request->category : 'repair',
            'vehicle_id' => $vehicle?->id, 'customer_id' => $vehicle?->customer_id, 'status' => 'pending', 'priority' => 'normal',
            'scheduled_date' => today(), 'is_billable' => $vehicle ? ! $vehicle->isCompanyStock() : true,
        ]);
        $job->setRelation('vehicle', $vehicle);

        return view('service-jobs.form', ['job' => $job, 'assigned' => []]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $job = $this->jobs->create(collect($data)->except(['employees'])->all(), $data['employees'] ?? []);
        $this->notifyAssignees($job, $data['employees'] ?? []);

        // Optional initial service lines from the chosen service types.
        foreach ((array) $request->input('service_type_ids', []) as $typeId) {
            if ($type = ServiceType::find($typeId)) {
                $this->jobs->addItem($job, ['item_type' => 'service', 'service_type_id' => $type->id, 'description' => $type->name,
                    'quantity' => 1, 'unit_cost' => $type->default_cost, 'unit_price' => $type->default_price]);
            }
        }

        return redirect()->route('service-jobs.show', $job)->with('success', "Job {$job->job_number} created.");
    }

    public function show(Request $request, ServiceJob $job)
    {
        $this->authorize('view', $job);
        $user = $request->user();

        return view('service-jobs.show', [
            'job' => $job->load(['vehicle', 'customer', 'supervisor', 'employees', 'items.part', 'items.employee', 'items.serviceType', 'images', 'invoice', 'tasks.employee']),
            'serviceTypes' => ServiceType::where('category', $job->category)->where('is_active', true)->orderBy('name')->get(),
            'canEdit' => $user->can('update', $job) && ! $job->isClosed(),
            'canFin' => $user->can('financials.view') || $user->can('invoices.view') || $user->can('service_jobs.approve'),
        ]);
    }

    public function edit(ServiceJob $job)
    {
        $this->authorize('update', $job);
        abort_if($job->isClosed(), 403, 'Closed jobs cannot be edited.');
        $job->load('vehicle', 'customer', 'supervisor');

        return view('service-jobs.form', ['job' => $job, 'assigned' => $job->employees->map(fn ($e) => ['id' => $e->id, 'text' => $e->full_name,
            'role' => $e->pivot->role, 'hours_worked' => (float) $e->pivot->hours_worked])->all()]);
    }

    public function update(Request $request, ServiceJob $job)
    {
        $this->authorize('update', $job);
        $data = $this->validated($request, $job);
        $this->guardStatus($request, $job, $data['status'] ?? $job->status);
        $before = $job->employees()->pluck('employees.id')->all();
        $this->jobs->update($job, collect($data)->except(['employees'])->all(), $data['employees'] ?? []);
        $this->notifyAssignees($job, array_diff_key($data['employees'] ?? [], array_flip($before)));

        return redirect()->route('service-jobs.show', $job)->with('success', 'Job updated.');
    }

    public function destroy(Request $request, ServiceJob $job)
    {
        $this->authorize('delete', $job);
        $this->jobs->delete($job);

        return $this->done($request, 'Job deleted and any issued parts returned to stock.', route('service-jobs.index', ['category' => $job->category]));
    }

    public function status(Request $request, ServiceJob $job)
    {
        $this->authorize('update', $job);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(config('autoshop.enums.service_statuses')))], 'notes' => ['nullable', 'string', 'max:500']]);
        $this->guardStatus($request, $job, $data['status']);
        $this->jobs->changeStatus($job, $data['status'], $data['notes'] ?? null);

        return $this->done($request, 'Job status changed to '.status_label($data['status']).'.');
    }

    /** Quality check / delivery require approval permission. */
    protected function guardStatus(Request $request, ServiceJob $job, string $status): void
    {
        if ($status !== $job->status && in_array($status, ['quality_checked', 'delivered', 'cancelled'], true)) {
            abort_unless($request->user()->can('approve', $job), 403, 'Only supervisors (service_jobs.approve) can set this status.');
        }
    }

    public function addItem(Request $request, ServiceJob $job)
    {
        $this->authorize('update', $job);
        $data = $request->validate([
            'item_type' => ['required', Rule::in(['service', 'labor', 'part', 'material'])],
            'service_type_id' => ['nullable', 'exists:service_types,id'],
            'part_id' => ['required_if:item_type,part', 'nullable', 'exists:parts,id'],
            'description' => ['required_unless:item_type,part', 'nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'employee_id' => ['nullable', 'exists:employees,id'],
        ]);
        if ($data['item_type'] === 'service' && ! empty($data['service_type_id']) && empty($data['description'])) {
            $data['description'] = ServiceType::find($data['service_type_id'])->name;
        }
        $allowNegative = $request->user()->can('parts.negative_stock') && $request->boolean('allow_negative');
        $this->jobs->addItem($job, $data, $allowNegative);

        return $this->done($request, $data['item_type'] === 'part' ? 'Part issued from stock and added to the job.' : 'Line added.');
    }

    public function removeItem(Request $request, ServiceJob $job, ServiceJobItem $item)
    {
        $this->authorize('update', $job);
        $this->jobs->removeItem($job, $item);

        return $this->done($request, $item->item_type === 'part' ? 'Part returned to stock.' : 'Line removed.');
    }

    public function uploadImages(Request $request, ServiceJob $job)
    {
        $this->authorize('update', $job);
        $request->validate(['stage' => ['required', 'in:before,after,progress'], 'images' => ['required', 'array', 'max:20'],
            'images.*' => Uploads::imageRule(true), 'caption' => ['nullable', 'string', 'max:255']]);
        foreach ($request->file('images') as $file) {
            $job->images()->create(['stage' => $request->stage, 'caption' => $request->caption, 'path' => Uploads::image($file, "service-jobs/{$job->id}")]);
        }

        return back()->with('success', 'Photos uploaded.');
    }

    public function destroyImage(ServiceJob $job, ServiceJobImage $image)
    {
        $this->authorize('update', $job);
        abort_unless($image->service_job_id === $job->id, 404);
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Photo deleted.');
    }

    public function checklist(Request $request, ServiceJob $job)
    {
        $this->authorize('update', $job);
        $data = $request->validate(['checklist' => ['array'], 'checklist.*.item' => ['required', 'string', 'max:255'],
            'checklist.*.done' => ['nullable', 'boolean'], 'new_item' => ['nullable', 'string', 'max:255']]);
        $list = collect($data['checklist'] ?? [])->map(fn ($c) => ['item' => $c['item'], 'done' => (bool) ($c['done'] ?? false)])->values()->all();
        if (! empty($data['new_item'])) {
            $list[] = ['item' => $data['new_item'], 'done' => false];
        }
        $job->update(['checklist' => $list]);

        return $this->done($request, 'Checklist saved.');
    }

    public function print(ServiceJob $job)
    {
        $this->authorize('view', $job);

        return view('service-jobs.print', ['job' => $job->load(['vehicle', 'customer', 'employees', 'items', 'supervisor'])]);
    }

    protected function validated(Request $request, ?ServiceJob $job = null): array
    {
        $data = $request->validate([
            'category' => [$job ? 'prohibited' : 'required', 'in:repair,interior,cleaning'],
            'vehicle_id' => [$job ? 'prohibited' : 'required', 'exists:vehicles,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'supervisor_id' => ['nullable', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(array_keys(config('autoshop.enums.service_statuses')))],
            'priority' => ['required', Rule::in(array_keys(config('autoshop.enums.priorities')))],
            'scheduled_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'completion_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'odometer_in' => ['nullable', 'integer', 'min:0'],
            'customer_charge' => ['nullable', 'numeric', 'min:0'],
            'is_billable' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'employees' => ['nullable', 'array'],
            'employees.*.role' => ['nullable', 'string', 'max:30'],
            'employees.*.hours_worked' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'service_type_ids' => ['nullable', 'array'],
            'service_type_ids.*' => ['exists:service_types,id'],
        ]);
        $data['is_billable'] = $request->boolean('is_billable');
        foreach (array_keys($data['employees'] ?? []) as $id) {
            abort_unless(Employee::whereKey($id)->exists(), 422, 'Unknown employee.');
        }
        unset($data['service_type_ids']);

        return $data;
    }

    protected function notifyAssignees(ServiceJob $job, array $employees): void
    {
        foreach (Employee::whereIn('id', array_keys($employees))->with('user')->get() as $e) {
            $e->user?->notify(new TaskAssigned("Assigned to job {$job->job_number}", $job->title.' — '.$job->vehicle?->title(), route('service-jobs.show', $job)));
        }
    }

    /** Bulk status change; each job is authorised individually (record-level + approval rules). */
    public function bulk(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer'],
            'action' => ['required', 'in:status'], 'value' => ['required', Rule::in(array_keys(config('autoshop.enums.service_statuses')))]]);
        $n = 0;
        $skipped = 0;
        foreach (ServiceJob::whereIn('id', $data['ids'])->get() as $job) {
            $restricted = in_array($data['value'], ['quality_checked', 'delivered', 'cancelled'], true);
            if (! $request->user()->can('update', $job) || ($restricted && ! $request->user()->can('approve', $job)) || $job->status === 'cancelled') {
                $skipped++;

                continue;
            }
            $this->jobs->changeStatus($job, $data['value'], 'Bulk update');
            $n++;
        }

        return $this->done($request, "{$n} job(s) updated".($skipped ? ", {$skipped} skipped (not permitted or cancelled)." : '.'));
    }
}
