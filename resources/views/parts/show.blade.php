@extends('layouts.app')
@section('title', $part->name)
@section('content')
@php $u = auth()->user(); @endphp
<x-page-header :title="$part->name" :breadcrumbs="['Parts' => route('parts.index'), $part->part_number => null]">
    <a href="{{ route('parts.label', $part) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-upc"></i> Label</a>
    @can('parts.edit')<a href="{{ route('parts.edit', $part) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>@endcan
    @can('parts.delete')<a href="{{ route('parts.destroy', $part) }}" data-method="DELETE" data-confirm="Delete this part?" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a>@endcan
</x-page-header>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card card-body"><div class="small text-secondary">On hand</div><div class="fs-3 fw-bold">{{ $part->quantity }} <small class="fs-6">{{ $part->unit }}</small></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body"><div class="small text-secondary">Reserved</div><div class="fs-3 fw-bold">{{ $part->quantity_reserved }}</div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body"><div class="small text-secondary">Available</div><div class="fs-3 fw-bold">{{ $part->quantity_available }}</div><x-status :status="$part->stock_status" /></div></div>
    <div class="col-6 col-md-3"><div class="card card-body"><div class="small text-secondary">{{ $canCosts ? 'Stock value (cost)' : 'Selling price' }}</div><div class="fs-3 fw-bold">{{ $canCosts ? money($part->stockValue()) : money($part->selling_price) }}</div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            @if($part->images->isNotEmpty())<div class="row g-1 p-2 gallery">@foreach($part->images as $img)<div class="col-4 position-relative"><img src="{{ $img->url() }}" alt="">
                @can('parts.edit')<a href="{{ route('parts.images.destroy', [$part, $img]) }}" data-method="DELETE" data-confirm="Delete image?" class="position-absolute top-0 end-0 m-1 btn btn-sm btn-light py-0"><i class="bi bi-x"></i></a>@endcan</div>@endforeach</div>@endif
            <dl class="dl-grid card-body">
                <dt>Part ID</dt><dd>{{ $part->part_number }}</dd><dt>OEM #</dt><dd>{{ $part->oem_number ?: '—' }}</dd>
                <dt>SKU / Barcode</dt><dd>{{ $part->sku ?: '—' }} / {{ $part->barcode ?: '—' }}</dd>
                <dt>Category</dt><dd>{{ $part->category?->name ?: '—' }}</dd><dt>Brand</dt><dd>{{ $part->brand ?: '—' }} {{ $part->manufacturer ? '· '.$part->manufacturer : '' }}</dd>
                <dt>Condition</dt><dd>{{ ucfirst($part->condition) }}</dd>
                @if($canCosts)<dt>Purchase cost</dt><dd>{{ money($part->purchase_cost) }}</dd>@endif
                <dt>Selling price</dt><dd>{{ money($part->selling_price) }}</dd>
                <dt>Min level</dt><dd>{{ $part->min_stock_level }}</dd>
                <dt>Location</dt><dd>{{ $part->defaultLocation?->name }} {{ $part->shelf ? '· shelf '.$part->shelf : '' }} {{ $part->bin ? '· bin '.$part->bin : '' }}</dd>
                <dt>Supplier</dt><dd>@if($part->supplier)<a href="{{ route('suppliers.show', $part->supplier) }}">{{ $part->supplier->name }}</a>@else — @endif</dd>
                <dt>Purchased</dt><dd>{{ fmt_date($part->purchase_date) ?: '—' }} {{ $part->purchase_invoice ? '· inv '.$part->purchase_invoice : '' }}</dd>
                <dt>Warranty</dt><dd>@if($part->warranty_months){{ $part->warranty_months }} months
                    @if($exp = $part->warrantyExpiresOn())<span class="{{ $exp->isPast() ? 'text-danger' : 'text-success' }}">(until {{ fmt_date($exp) }})</span>@endif @else — @endif</dd>
                <dt>Supervisor</dt><dd>{{ $part->supervisor?->full_name ?: '—' }}</dd>
                <dt>Fits</dt><dd>{{ $part->compatibilities->map->label()->implode(', ') ?: 'Universal / not specified' }}</dd>
            </dl>
            @if($part->description)<div class="card-body border-top small">{{ $part->description }}</div>@endif
            <div class="card-body border-top text-center bg-white rounded-bottom">{!! $barcode !!}</div>
        </div>
        <div class="card"><div class="card-header">Stock by location</div><table class="table table-sm mb-0">
            @forelse($part->stocks as $s)<tr><td>{{ $s->location?->name }}</td><td class="text-end fw-semibold">{{ $s->quantity }}</td></tr>
            @empty<tr><td class="text-secondary small">No stock recorded.</td></tr>@endforelse</table></div>
    </div>
    <div class="col-lg-7">
        @can('parts.adjust_stock')
        <div class="card mb-3" id="stock"><div class="card-header">Stock operation</div><div class="card-body">
            <form method="POST" action="{{ route('parts.stock', $part) }}" data-ajax data-reload id="stock-form" class="row g-2">@csrf
                <x-form.select name="operation" label="Operation" :options="['add' => 'Add stock', 'remove' => 'Remove stock', 'adjust' => 'Adjust to counted quantity', 'reserve' => 'Reserve', 'unreserve' => 'Release reservation', 'transfer' => 'Transfer between locations', 'damaged' => 'Mark damaged', 'return_to_supplier' => 'Return to supplier', 'assign_vehicle' => 'Assign to vehicle (not via job)']" col="col-md-6" />
                <div class="col-md-6"><label class="form-label" id="qty-label">Quantity</label><input type="number" name="quantity" min="0" class="form-control" required></div>
                <x-form.select name="location_id" label="Location" :options="$locations" :value="$part->default_location_id" col="col-md-6" />
                <div class="col-md-6" data-show="transfer"><x-form.select name="to_location_id" label="To location" :options="$locations" placeholder="—" /></div>
                <div class="col-md-6" data-show="add"><x-form.input name="unit_cost" type="number" step="0.01" min="0" label="Unit cost (optional)" /></div>
                <div class="col-md-6" data-show="assign_vehicle"><x-form.remote name="vehicle_id" type="vehicles" label="Vehicle" /></div>
                <x-form.input name="notes" label="Notes / reason" col="col-12" />
                @can('parts.negative_stock')<x-form.switch name="allow_negative" label="Allow going below zero (authorised override)" col="col-12" />@endcan
                <div class="col-12"><button class="btn btn-primary">Apply</button></div>
            </form>
        </div></div>
        @endcan
        <div class="card"><div class="card-header d-flex justify-content-between">Stock movement history <a href="{{ route('parts.movements', ['part_id' => $part->id]) }}" class="small">All</a></div>
            <div class="table-responsive" style="max-height:480px"><table class="table table-sm mb-0 small align-middle">
                <thead><tr><th>Date</th><th>Type</th><th class="text-end">Qty</th><th class="text-end">Balance</th><th>Location</th><th>By</th><th>Notes</th></tr></thead>
                <tbody>@forelse($movements as $m)<tr>
                    <td class="text-nowrap">{{ $m->created_at->format('Y-m-d H:i') }}</td><td>{{ \App\Models\StockMovement::TYPES[$m->type] ?? $m->type }}</td>
                    <td class="text-end {{ $m->quantity > 0 ? 'text-success' : ($m->quantity < 0 ? 'text-danger' : '') }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}{{ $m->reserved_change ? ' (res '.($m->reserved_change > 0 ? '+' : '').$m->reserved_change.')' : '' }}</td>
                    <td class="text-end">{{ $m->balance_after }}</td><td>{{ $m->location?->name }}</td><td>{{ $m->user?->name }}</td>
                    <td>{{ $m->notes }} @if($m->vehicle)<a href="{{ route('vehicles.show', $m->vehicle_id) }}">{{ $m->vehicle->stock_number }}</a>@endif</td></tr>
                @empty<tr><td colspan="7" class="text-secondary">No movements yet.</td></tr>@endforelse</tbody></table></div></div>
    </div>
</div>
@endsection
