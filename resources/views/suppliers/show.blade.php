@extends('layouts.app')
@section('title', $supplier->name)
@section('content')
@php $u = auth()->user(); $fin = $u->can('financials.view') || $u->can('purchase_orders.view'); @endphp
<x-page-header :title="$supplier->name" :breadcrumbs="['Suppliers' => route('suppliers.index'), $supplier->name => null]">
    @can('purchase_orders.create')<a href="{{ route('purchase-orders.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-cart-plus"></i> New PO</a>@endcan
    @can('suppliers.edit')<a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>@endcan
</x-page-header>
<div class="row g-3 mb-3">
    <div class="col-lg-4"><div class="card h-100"><dl class="dl-grid card-body">
        <dt>Code</dt><dd>{{ $supplier->code }}</dd><dt>Contact</dt><dd>{{ $supplier->contact_person ?: '—' }}</dd>
        <dt>Phone</dt><dd>{{ $supplier->phone ?: '—' }}</dd><dt>Email</dt><dd>{{ $supplier->email ?: '—' }}</dd>
        <dt>Website</dt><dd>@if($supplier->website)<a href="{{ $supplier->website }}" target="_blank" rel="noopener noreferrer">{{ $supplier->website }}</a>@else — @endif</dd>
        <dt>Address</dt><dd>{{ collect([$supplier->address, $supplier->city, $supplier->country])->filter()->implode(', ') ?: '—' }}</dd>
        <dt>Terms</dt><dd>{{ $supplier->payment_terms_days }} days</dd><dt>Status</dt><dd><x-status :label="$supplier->is_active ? 'Active' : 'Inactive'" :color="$supplier->is_active ? 'success' : 'secondary'" /></dd>
    </dl>@if($supplier->notes)<div class="card-body border-top small">{{ $supplier->notes }}</div>@endif</div></div>
    @if($fin)
    <div class="col-lg-8"><div class="row g-3">
        <div class="col-md-4"><div class="card card-body"><div class="small text-secondary">Total purchased</div><div class="fs-4 fw-bold">{{ money($supplier->totalPurchased()) }}</div></div></div>
        <div class="col-md-4"><div class="card card-body"><div class="small text-secondary">Amount paid</div><div class="fs-4 fw-bold">{{ money($supplier->totalPaid()) }}</div></div></div>
        <div class="col-md-4"><div class="card card-body"><div class="small text-secondary">Amount pending</div><div class="fs-4 fw-bold text-warning">{{ money($supplier->amountPending()) }}</div></div></div>
        <div class="col-12">@include('partials.attachments', ['model' => $supplier, 'type' => 'supplier', 'module' => 'suppliers'])</div>
    </div></div>
    @endif
</div>
<ul class="nav nav-tabs mb-3"><li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#orders">Purchase orders</a></li>
    @if($fin)<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#payments">Payments</a></li>@endif
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#parts">Parts supplied</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#vehicles">Vehicles</a></li></ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="orders"><div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>PO</th><th>Date</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Balance</th></tr></thead>
        <tbody>@forelse($orders as $o)<tr><td><a href="{{ route('purchase-orders.show', $o) }}">{{ $o->po_number }}</a></td><td>{{ fmt_date($o->order_date) }}</td><td><x-status :status="$o->status" /></td>
            <td class="text-end money">{{ money($o->total) }}</td><td class="text-end money">{{ money($o->balance) }}</td></tr>
        @empty<tr><td colspan="5" class="text-secondary small">No purchase orders.</td></tr>@endforelse</tbody></table></div></div></div>
    @if($fin)<div class="tab-pane fade" id="payments"><div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Date</th><th>PO</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th></tr></thead>
        <tbody>@forelse($payments as $p)<tr><td>{{ fmt_date($p->payment_date) }}</td><td>{{ $p->purchaseOrder?->po_number }}</td><td>{{ status_label($p->method) }}</td><td>{{ $p->reference }}</td><td class="text-end money">{{ money($p->amount) }}</td></tr>
        @empty<tr><td colspan="5" class="text-secondary small">No payments.</td></tr>@endforelse</tbody></table></div></div></div>@endif
    <div class="tab-pane fade" id="parts"><div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Part</th><th>OEM</th><th class="text-end">On hand</th><th>Status</th></tr></thead>
        <tbody>@forelse($parts as $p)<tr><td><a href="{{ route('parts.show', $p) }}">{{ $p->name }}</a></td><td>{{ $p->oem_number }}</td><td class="text-end">{{ $p->quantity }}</td><td><x-status :status="$p->stock_status" /></td></tr>
        @empty<tr><td colspan="4" class="text-secondary small">No parts.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="tab-pane fade" id="vehicles"><div class="card"><div class="list-group list-group-flush">@forelse($vehicles as $v)
        <a href="{{ route('vehicles.show', $v) }}" class="list-group-item list-group-item-action">{{ $v->stock_number }} — {{ $v->title() }} <span class="text-secondary small">{{ $v->vin }}</span></a>
    @empty<div class="list-group-item text-secondary small">No vehicles purchased from this supplier.</div>@endforelse</div></div></div>
</div>
@endsection
