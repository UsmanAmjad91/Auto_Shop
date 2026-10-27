@extends('layouts.app')
@section('title', $vehicle->title())
@section('content')
@php $u = auth()->user(); $enums = config('autoshop.enums'); @endphp
<x-page-header :title="$vehicle->title()" :breadcrumbs="['Vehicles' => route('vehicles.index'), $vehicle->stock_number => null]">
    @can('vehicles.edit')<a href="{{ route('vehicles.edit', $vehicle) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>@endcan
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i> Actions</button>
        <ul class="dropdown-menu dropdown-menu-end">
            @can('service_jobs.create')
                <li><a class="dropdown-item" href="{{ route('service-jobs.create', ['vehicle_id' => $vehicle->id, 'category' => 'repair']) }}"><i class="bi bi-tools me-2"></i>New repair job</a></li>
                <li><a class="dropdown-item" href="{{ route('service-jobs.create', ['vehicle_id' => $vehicle->id, 'category' => 'interior']) }}"><i class="bi bi-lamp me-2"></i>New interior job</a></li>
                <li><a class="dropdown-item" href="{{ route('service-jobs.create', ['vehicle_id' => $vehicle->id, 'category' => 'cleaning']) }}"><i class="bi bi-droplet-half me-2"></i>New wash / detailing job</a></li>
            @endcan
            @if($u->can('invoices.create') && $vehicle->isCompanyStock() && ! $vehicle->sale_invoice_id)
                <li><a class="dropdown-item" href="{{ route('invoices.create', ['type' => 'vehicle_sale', 'vehicle_id' => $vehicle->id]) }}"><i class="bi bi-bag-check me-2"></i>Sell vehicle (create invoice)</a></li>
            @endif
            @can('vehicles.edit')<li><a class="dropdown-item" href="{{ route('vehicles.inspections.create', $vehicle) }}"><i class="bi bi-clipboard-check me-2"></i>New inspection</a></li>@endcan
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ route('vehicles.sheet', $vehicle) }}" target="_blank"><i class="bi bi-printer me-2"></i>Print info sheet</a></li>
            <li><a class="dropdown-item" href="{{ route('vehicles.sheet', [$vehicle, 'pdf' => 1]) }}"><i class="bi bi-filetype-pdf me-2"></i>Info sheet PDF</a></li>
            <li><a class="dropdown-item" href="{{ route('vehicles.label', $vehicle) }}" target="_blank"><i class="bi bi-qr-code me-2"></i>Stock label / QR</a></li>
            @can('vehicles.delete')<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="{{ route('vehicles.destroy', $vehicle) }}" data-method="DELETE" data-confirm="Delete this vehicle?"><i class="bi bi-trash me-2"></i>Delete</a></li>@endcan
        </ul>
    </div>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            @if($vehicle->images->isNotEmpty())
                <div id="vehicleCarousel" class="carousel slide" data-bs-ride="false">
                    <div class="carousel-inner">@foreach($vehicle->images as $i => $img)<div class="carousel-item {{ $i === 0 ? 'active' : '' }}"><img src="{{ $img->url() }}" class="d-block w-100" style="height:240px;object-fit:cover;border-radius:.65rem .65rem 0 0" alt="Vehicle photo {{ $i + 1 }}"></div>@endforeach</div>
                    @if($vehicle->images->count() > 1)
                        <button class="carousel-control-prev" type="button" data-bs-target="#vehicleCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span><span class="visually-hidden">Previous</span></button>
                        <button class="carousel-control-next" type="button" data-bs-target="#vehicleCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span><span class="visually-hidden">Next</span></button>
                    @endif
                </div>
            @else
                <div class="d-grid text-secondary bg-body-tertiary" style="height:200px;place-items:center;border-radius:.65rem .65rem 0 0"><i class="bi bi-car-front display-4"></i></div>
            @endif
            <div class="card-body">
                <div class="font-monospace fw-semibold">{{ $vehicle->vin }}</div>
                <div class="small text-secondary mb-2">Stock {{ $vehicle->stock_number }} · {{ $enums['ownership_types'][$vehicle->ownership_type] ?? '' }}</div>
                <div class="d-flex flex-wrap gap-1">
                    <x-status :label="$statuses[$vehicle->repair_status] ?? status_label($vehicle->repair_status)" :color="$statusColors[$vehicle->repair_status] ?? 'secondary'" />
                    <x-status :status="$vehicle->inventory_status" />
                    <x-status :status="$vehicle->payment_status" :label="$enums['vehicle_payment_statuses'][$vehicle->payment_status] ?? null" />
                </div>
                <div class="d-flex justify-content-between align-items-end mt-3">
                    <div><div class="small text-secondary">{{ $vehicle->sale_price !== null ? 'Sold for' : 'Asking price' }}</div>
                        <div class="fs-4 fw-bold">{{ $vehicle->sale_price !== null ? money($vehicle->sale_price) : ($vehicle->asking_price !== null ? money($vehicle->asking_price) : '—') }}</div></div>
                    <div class="bg-white p-1 rounded" style="width:72px">{!! $qr !!}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-body">
            <div class="row">
                <div class="col-md-6"><dl class="dl-grid">
                    <dt>Year / Make</dt><dd>{{ $vehicle->year }} {{ $vehicle->make }}</dd>
                    <dt>Model / Trim</dt><dd>{{ $vehicle->model }} {{ $vehicle->trim }}</dd>
                    <dt>Body</dt><dd>{{ $vehicle->body_type }} {{ $vehicle->drive_type ? '· '.$vehicle->drive_type : '' }}</dd>
                    <dt>Engine</dt><dd>{{ $vehicle->engine ?: '—' }}</dd>
                    <dt>Transmission</dt><dd>{{ $vehicle->transmission ?: '—' }}</dd>
                    <dt>Fuel</dt><dd>{{ $vehicle->fuel_type ?: '—' }}</dd>
                    <dt>Colours</dt><dd>{{ $vehicle->exterior_color ?: '—' }} / {{ $vehicle->interior_color ?: '—' }}</dd>
                    <dt>Mileage</dt><dd>{{ $vehicle->mileage !== null ? number_format($vehicle->mileage) : '—' }}</dd>
                </dl></div>
                <div class="col-md-6"><dl class="dl-grid">
                    <dt>Plate</dt><dd>{{ $vehicle->license_plate ?: '—' }}</dd>
                    <dt>Registration</dt><dd>{{ $vehicle->registration_number ?: '—' }} {{ $vehicle->registration_state }}
                        @if($vehicle->registration_expiry)<span class="{{ $vehicle->registration_expiry->isPast() ? 'text-danger' : '' }}">(exp. {{ fmt_date($vehicle->registration_expiry) }})</span>@endif</dd>
                    <dt>Condition</dt><dd>{{ $enums['conditions'][$vehicle->condition] ?? '—' }}</dd>
                    <dt>Location</dt><dd>{{ $vehicle->location ?: '—' }}</dd>
                    <dt>Purchased</dt><dd>{{ fmt_date($vehicle->purchase_date) ?: '—' }} {{ $vehicle->purchase_source ? '· '.($enums['purchase_sources'][$vehicle->purchase_source] ?? '') : '' }}</dd>
                    <dt>Supplier</dt><dd>@if($vehicle->supplier)<a href="{{ route('suppliers.show', $vehicle->supplier) }}">{{ $vehicle->supplier->name }}</a>@else — @endif</dd>
                    <dt>Owner</dt><dd>@if($vehicle->owner)<a href="{{ route('customers.show', $vehicle->owner) }}">{{ $vehicle->owner->display_name }}</a>@else — @endif</dd>
                    <dt>Buyer</dt><dd>@if($vehicle->buyer)<a href="{{ route('customers.show', $vehicle->buyer) }}">{{ $vehicle->buyer->display_name }}</a> ({{ fmt_date($vehicle->sold_at) }})@else — @endif</dd>
                    <dt>Assigned</dt><dd>{{ $vehicle->assignedEmployee?->full_name ?: '—' }}</dd>
                </dl></div>
            </div>
            @foreach($customFields as $f)
                @if(isset($vehicle->custom_fields[$f->key]))<span class="badge text-bg-light border me-1">{{ $f->label }}: {{ is_bool($vehicle->custom_fields[$f->key]) ? ($vehicle->custom_fields[$f->key] ? 'Yes' : 'No') : $vehicle->custom_fields[$f->key] }}</span>@endif
            @endforeach
            @if($vehicle->description)<p class="mt-2 mb-1 small">{{ $vehicle->description }}</p>@endif
            @if($vehicle->notes)<p class="mb-0 small text-secondary"><i class="bi bi-sticky"></i> {{ $vehicle->notes }}</p>@endif

            @if($u->can('vehicles.change_status') || $u->can('vehicles.edit'))
            <form method="POST" action="{{ route('vehicles.status', $vehicle) }}" class="row g-2 mt-3 border-top pt-3" data-ajax data-reload>@csrf
                <input type="hidden" name="field" value="repair_status">
                <div class="col-md-4"><select name="status" class="form-select form-select-sm" aria-label="New repair status">@foreach($statuses as $k => $l)<option value="{{ $k }}" @selected($vehicle->repair_status === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-md-5"><input name="notes" class="form-control form-control-sm" placeholder="Status note (optional)"></div>
                <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100">Update repair status</button></div>
            </form>
            @endif
        </div></div>
    </div>
</div>

<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto" role="tablist">
    @if($canCosts)<li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#financials" role="tab">Financials</a></li>@endif
    <li class="nav-item"><a class="nav-link {{ $canCosts ? '' : 'active' }}" data-bs-toggle="tab" href="#jobs" role="tab">Service jobs <span class="badge text-bg-secondary">{{ $vehicle->serviceJobs->count() }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#photos" role="tab">Photos <span class="badge text-bg-secondary">{{ $vehicle->images->count() }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#documents" role="tab">Documents <span class="badge text-bg-secondary">{{ $vehicle->documents->count() }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#parts" role="tab">Parts used</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#inspections" role="tab">Inspections</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#history" role="tab">Status history</a></li>
    @can('invoices.view')<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#invoices" role="tab">Invoices</a></li>@endcan
</ul>
<div class="tab-content">
    @if($canCosts)
    <div class="tab-pane fade show active" id="financials" role="tabpanel">
        <div class="row g-3">
            <div class="col-lg-5"><div class="card"><div class="card-header">Cost breakdown</div>
                <table class="table mb-0"><tbody>
                    <tr><td>Purchase cost</td><td class="text-end money">{{ money($vehicle->purchase_price) }}</td></tr>
                    <tr><td>Parts (from service jobs)</td><td class="text-end money">{{ money($vehicle->parts_cost) }}</td></tr>
                    <tr><td>Repair labor</td><td class="text-end money">{{ money($vehicle->labor_cost) }}</td></tr>
                    <tr><td>Interior repair</td><td class="text-end money">{{ money($vehicle->interior_cost) }}</td></tr>
                    <tr><td>Wash / detailing</td><td class="text-end money">{{ money($vehicle->detailing_cost) }}</td></tr>
                    <tr><td>Other expenses</td><td class="text-end money">{{ money($vehicle->other_cost) }}</td></tr>
                    <tr class="fw-bold table-light"><td>Total investment</td><td class="text-end money">{{ money($vehicle->total_investment) }}</td></tr>
                    <tr><td>Asking price</td><td class="text-end money">{{ $vehicle->asking_price !== null ? money($vehicle->asking_price) : '—' }}</td></tr>
                    <tr><td>Selling price</td><td class="text-end money">{{ $vehicle->sale_price !== null ? money($vehicle->sale_price) : '—' }}</td></tr>
                    @php $p = $vehicle->profit(); @endphp
                    <tr class="fw-bold"><td>{{ $vehicle->isProfitEstimated() ? 'Estimated profit (at asking price)' : 'Actual profit' }}</td>
                        <td class="text-end money {{ ($p ?? 0) >= 0 ? 'text-profit' : 'text-loss' }}">{{ $p !== null ? money($p) : '—' }}</td></tr>
                    @if($vehicle->sale_invoice_id || $vehicle->invoices->isNotEmpty())
                        <tr><td>Amount paid</td><td class="text-end money">{{ money($vehicle->amount_paid) }}</td></tr>
                        <tr><td>Remaining balance</td><td class="text-end money">{{ money($vehicle->balance_due) }}</td></tr>
                        @if($vehicle->saleInvoice?->financing)<tr><td colspan="2" class="small text-secondary">Financing: {{ money($vehicle->saleInvoice->financing->financed_amount) }} with {{ $vehicle->saleInvoice->financing->lender_name }} ({{ $vehicle->saleInvoice->financing->status }})</td></tr>@endif
                    @endif
                </tbody></table>
                <div class="card-footer small text-secondary">Costs are derived from service jobs and expenses below — each source is counted once.</div>
            </div></div>
            <div class="col-lg-7"><div class="card"><div class="card-header d-flex justify-content-between">Other expenses</div>
                <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Date</th><th>Category</th><th>Description</th><th class="text-end">Amount</th><th></th></tr></thead>
                    <tbody>@forelse($vehicle->expenses as $e)<tr><td>{{ fmt_date($e->expense_date) }}</td><td>{{ $enums['expense_categories'][$e->category] ?? $e->category }}</td>
                        <td class="small">{{ $e->description }} {{ $e->supplier ? '· '.$e->supplier->name : '' }} {{ $e->reference }}</td><td class="text-end money">{{ money($e->amount) }}</td>
                        <td>@can('vehicles.edit')<a href="{{ route('vehicles.expenses.destroy', [$vehicle, $e]) }}" data-method="DELETE" data-confirm="Remove expense?" class="text-danger"><i class="bi bi-x-lg"></i></a>@endcan</td></tr>
                    @empty<tr><td colspan="5" class="text-secondary small">No other expenses.</td></tr>@endforelse</tbody></table></div>
                @can('vehicles.edit')
                <form method="POST" action="{{ route('vehicles.expenses.store', $vehicle) }}" class="card-footer row g-2">@csrf
                    <div class="col-md-3"><select name="category" class="form-select form-select-sm">@foreach($enums['expense_categories'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-3"><input name="description" class="form-control form-control-sm" placeholder="Description"></div>
                    <div class="col-md-2"><input name="amount" type="number" step="0.01" min="0.01" class="form-control form-control-sm" placeholder="Amount" required></div>
                    <div class="col-md-2"><input name="expense_date" type="date" value="{{ today()->toDateString() }}" class="form-control form-control-sm" required></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Add</button></div>
                </form>
                @endcan
            </div></div>
        </div>
    </div>
    @endif

    <div class="tab-pane fade {{ $canCosts ? '' : 'show active' }}" id="jobs" role="tabpanel"><div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
        <thead><tr><th>Job</th><th>Category</th><th>Title</th><th>Status</th><th>Employees</th><th>Created</th>@if($canCosts)<th class="text-end">Cost</th>@endif</tr></thead>
        <tbody>@forelse($vehicle->serviceJobs as $j)<tr>
            <td><a href="{{ route('service-jobs.show', $j) }}">{{ $j->job_number }}</a></td><td>{{ $j->categoryLabel() }}</td><td>{{ $j->title }}</td>
            <td><x-status :status="$j->status" /></td><td class="small">{{ $j->employees->pluck('full_name')->implode(', ') }}</td><td>{{ fmt_date($j->created_at) }}</td>
            @if($canCosts)<td class="text-end money">{{ money($j->total_cost) }}</td>@endif</tr>
        @empty<tr><td colspan="7" class="text-secondary small">No service jobs yet.</td></tr>@endforelse</tbody></table></div></div></div>

    <div class="tab-pane fade" id="photos" role="tabpanel"><div class="card card-body">
        <div class="row g-2 gallery">@forelse($vehicle->images as $img)
            <div class="col-6 col-md-3 col-xl-2"><div class="position-relative">
                <a href="{{ $img->url() }}" target="_blank"><img src="{{ $img->url() }}" alt=""></a>
                @if($img->is_primary)<span class="badge text-bg-primary position-absolute top-0 start-0 m-1">Primary</span>@endif
                @can('vehicles.edit')<div class="d-flex gap-1 mt-1">
                    @unless($img->is_primary)<a href="{{ route('vehicles.images.primary', [$vehicle, $img]) }}" data-method="POST" class="btn btn-sm btn-outline-secondary flex-fill">Make primary</a>@endunless
                    <a href="{{ route('vehicles.images.destroy', [$vehicle, $img]) }}" data-method="DELETE" data-confirm="Delete photo?" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a></div>@endcan
            </div></div>
        @empty<div class="text-secondary small">No photos yet.</div>@endforelse</div>
        @can('vehicles.edit')<form method="POST" action="{{ route('vehicles.images.store', $vehicle) }}" enctype="multipart/form-data" class="input-group mt-3">@csrf
            <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="form-control" required><button class="btn btn-primary">Upload photos</button></form>@endcan
    </div></div>

    <div class="tab-pane fade" id="documents" role="tabpanel"><div class="card">
        <div class="table-responsive"><table class="table mb-0 align-middle">
            <thead><tr><th>Title</th><th>Type</th><th>File</th><th>Expires</th><th>Uploaded</th><th></th></tr></thead>
            <tbody>@forelse($vehicle->documents as $d)<tr><td>{{ $d->title }}</td><td>{{ \App\Models\VehicleDocument::TYPES[$d->document_type] ?? $d->document_type }}</td>
                <td class="small">{{ $d->original_name }} ({{ number_format($d->size / 1024) }} KB)</td>
                <td class="{{ $d->expires_at?->isPast() ? 'text-danger' : '' }}">{{ fmt_date($d->expires_at) }}</td><td class="small">{{ $d->uploader?->name }} · {{ fmt_date($d->created_at) }}</td>
                <td class="text-end text-nowrap"><a href="{{ route('vehicles.documents.show', [$vehicle, $d]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a>
                    @can('vehicles.edit')<a href="{{ route('vehicles.documents.destroy', [$vehicle, $d]) }}" data-method="DELETE" data-confirm="Delete document?" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a>@endcan</td></tr>
            @empty<tr><td colspan="6" class="text-secondary small">No documents.</td></tr>@endforelse</tbody></table></div>
        @can('vehicles.edit')<form method="POST" action="{{ route('vehicles.documents.store', $vehicle) }}" enctype="multipart/form-data" class="card-footer row g-2">@csrf
            <div class="col-md-3"><select name="document_type" class="form-select form-select-sm">@foreach(\App\Models\VehicleDocument::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-3"><input name="title" class="form-control form-control-sm" placeholder="Title" required></div>
            <div class="col-md-2"><input name="expires_at" type="date" class="form-control form-control-sm" title="Expiry (optional)"></div>
            <div class="col-md-3"><input type="file" name="file" class="form-control form-control-sm" required></div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Upload</button></div>
        </form>@endcan
    </div></div>

    <div class="tab-pane fade" id="parts" role="tabpanel"><div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Date</th><th>Part</th><th>Movement</th><th class="text-end">Qty</th>@if($canCosts)<th class="text-end">Unit cost</th>@endif<th>Notes</th></tr></thead>
        <tbody>@forelse($parts as $m)<tr><td>{{ $m->created_at->format('Y-m-d') }}</td><td><a href="{{ route('parts.show', $m->part_id) }}">{{ $m->part?->name }}</a></td>
            <td>{{ \App\Models\StockMovement::TYPES[$m->type] ?? $m->type }}</td><td class="text-end">{{ -$m->quantity }}</td>
            @if($canCosts)<td class="text-end money">{{ money($m->unit_cost) }}</td>@endif<td class="small">{{ $m->notes }}</td></tr>
        @empty<tr><td colspan="6" class="text-secondary small">No parts recorded against this vehicle.</td></tr>@endforelse</tbody></table></div></div></div>

    <div class="tab-pane fade" id="inspections" role="tabpanel"><div class="card">
        <div class="list-group list-group-flush">@forelse($vehicle->inspections as $i)
            <a href="{{ route('vehicles.inspections.show', [$vehicle, $i]) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                <span>{{ fmt_date($i->inspection_date) }} · {{ status_label($i->type) }} · {{ $i->inspector?->full_name }}</span><x-status :status="$i->result" /></a>
        @empty<div class="list-group-item text-secondary small">No inspections recorded.</div>@endforelse</div>
        @can('vehicles.edit')<div class="card-footer"><a href="{{ route('vehicles.inspections.create', $vehicle) }}" class="btn btn-sm btn-primary"><i class="bi bi-clipboard-check"></i> New inspection</a></div>@endcan
    </div></div>

    <div class="tab-pane fade" id="history" role="tabpanel"><div class="card card-body"><ul class="timeline">
        @forelse($vehicle->statusHistory as $h)
            <li><div class="small"><strong>{{ $h->field === 'repair_status' ? 'Repair' : 'Inventory' }}:</strong>
                {{ $h->from_status ? ($statuses[$h->from_status] ?? status_label($h->from_status)).' → ' : '' }}<strong>{{ $statuses[$h->to_status] ?? status_label($h->to_status) }}</strong></div>
                <div class="small text-secondary">{{ $h->created_at->format('Y-m-d H:i') }} · {{ $h->user?->name ?? 'System' }}{{ $h->employee ? ' · '.$h->employee->full_name : '' }}{{ $h->notes ? ' — '.$h->notes : '' }}</div></li>
        @empty <li class="border-0 small text-secondary">No history.</li> @endforelse
    </ul></div></div>

    @can('invoices.view')
    <div class="tab-pane fade" id="invoices" role="tabpanel"><div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Invoice</th><th>Type</th><th>Date</th><th>Customer</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Balance</th></tr></thead>
        <tbody>@forelse($vehicle->invoices as $inv)<tr><td><a href="{{ route('invoices.show', $inv) }}">{{ $inv->invoice_number }}</a></td><td>{{ $enums['invoice_types'][$inv->type] }}</td>
            <td>{{ fmt_date($inv->invoice_date) }}</td><td>{{ $inv->customer_name }}</td><td><x-status :status="$inv->status" /></td>
            <td class="text-end money">{{ money($inv->total) }}</td><td class="text-end money">{{ money($inv->balance) }}</td></tr>
        @empty<tr><td colspan="7" class="text-secondary small">No invoices.</td></tr>@endforelse</tbody></table></div></div></div>
    @endcan
</div>
@endsection
