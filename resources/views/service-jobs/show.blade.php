@extends('layouts.app')
@section('title', $job->job_number)
@section('content')
@php
    $u = auth()->user();
    $statuses = config('autoshop.enums.service_statuses');
    $canApprove = $u->can('approve', $job);
@endphp
<x-page-header :title="$job->job_number.' — '.$job->title" :breadcrumbs="[$job->categoryLabel() => route('service-jobs.index', ['category' => $job->category]), $job->job_number => null]">
    <a href="{{ route('service-jobs.print', $job) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Job card</a>
    @if($canEdit)<a href="{{ route('service-jobs.edit', $job) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>@endif
    @if($u->can('invoices.create') && $job->is_billable && ! $job->invoice_id && $job->status !== 'cancelled')
        <a href="{{ route('invoices.create', ['service_job_id' => $job->id]) }}" class="btn btn-sm btn-success"><i class="bi bi-receipt"></i> Create invoice</a>
    @endif
    @if($job->invoice)<a href="{{ route('invoices.show', $job->invoice) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-receipt"></i> {{ $job->invoice->invoice_number }}</a>@endif
    @can('tasks.create')<button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#taskModal"><i class="bi bi-list-check"></i> Task</button>@endcan
    @can('delete', $job)<a href="{{ route('service-jobs.destroy', $job) }}" data-method="DELETE" data-confirm="Delete this job? Issued parts will be returned to stock." class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a>@endcan
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex flex-wrap gap-4 mb-3">
                <div><div class="small text-secondary">Status</div><x-status :status="$job->status" class="fs-6" /></div>
                <div><div class="small text-secondary">Category</div>{{ $job->categoryLabel() }}</div>
                <div><div class="small text-secondary">Priority</div>{{ ucfirst($job->priority) }}</div>
                <div><div class="small text-secondary">Scheduled</div>{{ fmt_date($job->scheduled_date) ?: '—' }}</div>
                <div><div class="small text-secondary">Started</div>{{ $job->start_date ? fmt_date($job->start_date, true) : '—' }}</div>
                <div><div class="small text-secondary">Completed</div>{{ $job->completion_date ? fmt_date($job->completion_date, true) : '—' }}</div>
            </div>
            <dl class="dl-grid">
                <dt>Vehicle</dt><dd><a href="{{ route('vehicles.show', $job->vehicle) }}">{{ $job->vehicle->title() }}</a> · <span class="font-monospace">{{ $job->vehicle->vin }}</span> · {{ $job->vehicle->stock_number }}</dd>
                <dt>Customer</dt><dd>@if($job->customer)<a href="{{ route('customers.show', $job->customer) }}">{{ $job->customer->display_name }}</a> {{ $job->customer->phone }}@else Internal (company stock) @endif</dd>
                <dt>Supervisor</dt><dd>{{ $job->supervisor?->full_name ?? '—' }}</dd>
                <dt>Employees</dt><dd>@forelse($job->employees as $e)<span class="badge text-bg-light border me-1">{{ $e->full_name }} · {{ $e->pivot->role }} · {{ $e->pivot->hours_worked }}h</span>@empty — @endforelse</dd>
                <dt>Odometer in</dt><dd>{{ $job->odometer_in ? number_format($job->odometer_in) : '—' }}</dd>
                <dt>Billable</dt><dd>{{ $job->is_billable ? 'Yes' : 'No (internal)' }}</dd>
            </dl>
            @if($job->description)<p class="mt-2 mb-1">{{ $job->description }}</p>@endif
            @if($job->notes)<p class="small text-secondary mb-0"><i class="bi bi-sticky"></i> {{ $job->notes }}</p>@endif
        </div>
        @if($u->can('update', $job) && $job->status !== 'cancelled')
        <div class="card-footer d-flex flex-wrap gap-1 align-items-center">
            <span class="small text-secondary me-2">Move to:</span>
            @foreach($statuses as $k => $l)
                @continue($k === $job->status)
                @if(in_array($k, ['quality_checked', 'delivered', 'cancelled']) && ! $canApprove) @continue @endif
                <a href="{{ route('service-jobs.status', $job) }}" data-method="POST" data-payload='{"status":"{{ $k }}"}' class="btn btn-sm btn-outline-{{ $k === 'cancelled' ? 'danger' : status_color($k) }}"
                   @if($k === 'cancelled') data-confirm="Cancel this job? Parts will be returned to stock." @endif>{{ $l }}</a>
            @endforeach
        </div>
        @endif
        </div>
    </div>
    <div class="col-lg-4">
        @if($canFin)
        <div class="card mb-3"><div class="card-header">Costs & charges</div><table class="table table-sm mb-0">
            <tr><td>Labor / services</td><td class="text-end money">{{ money($job->labor_cost) }}</td></tr>
            <tr><td>Materials</td><td class="text-end money">{{ money($job->material_cost) }}</td></tr>
            <tr><td>Parts</td><td class="text-end money">{{ money($job->parts_cost) }}</td></tr>
            <tr class="fw-bold"><td>Total cost</td><td class="text-end money">{{ money($job->total_cost) }}</td></tr>
            <tr class="fw-bold table-light"><td>Customer charge</td><td class="text-end money">{{ money($job->customer_charge) }}</td></tr>
            <tr><td>Margin</td><td class="text-end money {{ $job->customer_charge - $job->total_cost >= 0 ? 'text-profit' : 'text-loss' }}">{{ money($job->customer_charge - $job->total_cost) }}</td></tr>
        </table><div class="card-footer small text-secondary">Costs roll into the vehicle's investment automatically.</div></div>
        @endif
        <div class="card"><div class="card-header">Checklist</div><div class="card-body">
            <form method="POST" action="{{ route('service-jobs.checklist', $job) }}" data-ajax data-reload>@csrf @method('PUT')
                @foreach($job->checklist ?? [] as $i => $c)
                    <div class="form-check"><input type="hidden" name="checklist[{{ $i }}][item]" value="{{ $c['item'] }}"><input type="hidden" name="checklist[{{ $i }}][done]" value="0">
                        <input class="form-check-input" type="checkbox" name="checklist[{{ $i }}][done]" value="1" id="chk{{ $i }}" @checked($c['done']) @disabled(! $canEdit) onchange="this.form.requestSubmit()">
                        <label class="form-check-label small {{ $c['done'] ? 'text-decoration-line-through text-secondary' : '' }}" for="chk{{ $i }}">{{ $c['item'] }}</label></div>
                @endforeach
                @if($canEdit)<div class="input-group input-group-sm mt-2"><input name="new_item" class="form-control" placeholder="Add checklist item"><button class="btn btn-outline-secondary">Add</button></div>@endif
            </form>
        </div></div>
    </div>
</div>

<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">Services, labor, parts & materials
    @if($canEdit)<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal"><i class="bi bi-plus"></i> Add line</button>@endif</div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Type</th><th>Description</th><th>By</th><th class="text-end">Qty</th>@if($canFin)<th class="text-end">Unit cost</th><th class="text-end">Unit price</th><th class="text-end">Cost</th><th class="text-end">Price</th>@endif<th></th></tr></thead>
        <tbody>@forelse($job->items as $i)<tr>
            <td><span class="badge text-bg-{{ ['part' => 'primary', 'labor' => 'info', 'service' => 'success', 'material' => 'warning'][$i->item_type] }}">{{ \App\Models\ServiceJobItem::TYPES[$i->item_type] }}</span></td>
            <td>{{ $i->description }} @if($i->part)<a href="{{ route('parts.show', $i->part_id) }}" class="small">({{ $i->part->part_number }})</a>@endif</td>
            <td class="small">{{ $i->employee?->full_name }}</td>
            <td class="text-end">{{ rtrim(rtrim((string) $i->quantity, '0'), '.') }}</td>
            @if($canFin)<td class="text-end money">{{ money($i->unit_cost) }}</td><td class="text-end money">{{ money($i->unit_price) }}</td><td class="text-end money">{{ money($i->total_cost) }}</td><td class="text-end money">{{ money($i->total_price) }}</td>@endif
            <td class="text-end">@if($canEdit)<a href="{{ route('service-jobs.items.destroy', [$job, $i]) }}" data-method="DELETE" data-confirm="{{ $i->item_type === 'part' ? 'Remove part and return it to stock?' : 'Remove line?' }}" class="text-danger"><i class="bi bi-x-lg"></i></a>@endif</td>
        </tr>@empty<tr><td colspan="9" class="text-secondary small">No lines yet. Add services, labor, parts (deducted from stock) or materials.</td></tr>@endforelse</tbody>
    </table></div></div>

<div class="row g-3">
    @foreach(['before' => 'Before photos', 'progress' => 'Progress photos', 'after' => 'After photos'] as $stage => $label)
    <div class="col-lg-4"><div class="card h-100"><div class="card-header">{{ $label }}</div><div class="card-body">
        <div class="row g-2 gallery">@forelse($job->images->where('stage', $stage) as $img)
            <div class="col-6 position-relative"><a href="{{ $img->url() }}" target="_blank"><img src="{{ $img->url() }}" alt="{{ $img->caption }}"></a>
                @if($canEdit)<a href="{{ route('service-jobs.images.destroy', [$job, $img]) }}" data-method="DELETE" data-confirm="Delete photo?" class="position-absolute top-0 end-0 m-1 btn btn-sm btn-light py-0"><i class="bi bi-x"></i></a>@endif</div>
        @empty<div class="small text-secondary">None.</div>@endforelse</div>
        @if($canEdit)<form method="POST" action="{{ route('service-jobs.images.store', $job) }}" enctype="multipart/form-data" class="input-group input-group-sm mt-2">@csrf
            <input type="hidden" name="stage" value="{{ $stage }}"><input type="file" name="images[]" multiple accept="image/*" capture="environment" class="form-control" required><button class="btn btn-outline-primary">Upload</button></form>@endif
    </div></div></div>
    @endforeach
</div>

@if($job->tasks->isNotEmpty())
<div class="card mt-3"><div class="card-header">Tasks</div><ul class="list-group list-group-flush small">@foreach($job->tasks as $t)
    <li class="list-group-item d-flex justify-content-between"><span>{{ $t->title }} — {{ $t->employee?->full_name }}</span><x-status :status="$t->status" /></li>@endforeach</ul></div>
@endif

@if($canEdit)
@push('modals')
<x-modal id="itemModal" title="Add line to job">
    <form method="POST" action="{{ route('service-jobs.items.store', $job) }}" id="job-item-form" data-ajax data-reload>@csrf
        <div class="row g-2">
            <x-form.select name="item_type" label="Type" :options="\App\Models\ServiceJobItem::TYPES" value="service" col="col-12" />
            <div class="col-12" data-show="service"><label class="form-label">Service type</label>
                <select name="service_type_id" class="form-select"><option value="">—</option>
                    @foreach($serviceTypes as $t)<option value="{{ $t->id }}" data-price="{{ $t->default_price }}" data-cost="{{ $t->default_cost }}">{{ $t->name }}</option>@endforeach</select></div>
            <div class="col-12" data-show="part"><x-form.remote name="part_id" type="parts" label="Part (deducted from stock)" /><div class="small text-secondary" id="part-available"></div></div>
            <x-form.input name="description" label="Description" col="col-12" placeholder="Auto-filled for parts" />
            <x-form.input name="quantity" type="number" step="0.01" min="0.01" label="Quantity / hours" value="1" required col="col-4" />
            <x-form.input name="unit_cost" type="number" step="0.01" min="0" label="Unit cost" col="col-4" help="Internal cost" />
            <x-form.input name="unit_price" type="number" step="0.01" min="0" label="Unit price" col="col-4" help="Charge to customer" />
            <x-form.remote name="employee_id" type="employees" label="Performed by" col="col-12" />
            @can('parts.negative_stock')<div class="col-12" data-show="part"><x-form.switch name="allow_negative" label="Allow negative stock (override)" /></div>@endcan
        </div>
        <div class="text-end mt-3"><button class="btn btn-primary">Add line</button></div>
    </form>
</x-modal>
@endpush
@endif
@can('tasks.create')@include('tasks._modal', ['vehicleId' => $job->vehicle_id, 'vehicleName' => $job->vehicle->title(), 'jobId' => $job->id, 'jobName' => $job->job_number])@endcan
@endsection
