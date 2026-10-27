@extends('layouts.app')
@section('title', $customer->display_name)
@section('content')
@php $u = auth()->user(); @endphp
<x-page-header :title="$customer->display_name" :breadcrumbs="['Customers' => route('customers.index'), $customer->code => null]">
    @can('invoices.create')<a href="{{ route('invoices.create', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-receipt"></i> New invoice</a>@endcan
    @can('payments.create')<a href="{{ route('payments.create', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-cash-coin"></i> Record payment</a>@endcan
    @can('invoices.view')<a href="{{ route('customers.statement', $customer) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-text"></i> Statement</a>@endcan
    @can('customers.edit')<a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>@endcan
</x-page-header>
<div class="row g-3 mb-3">
    <div class="col-lg-4"><div class="card h-100"><dl class="dl-grid card-body">
        <dt>Code</dt><dd>{{ $customer->code }} <x-status :label="$customer->is_active ? 'Active' : 'Inactive'" :color="$customer->is_active ? 'success' : 'secondary'" /></dd>
        <dt>Type</dt><dd>{{ ucfirst($customer->type) }}</dd>
        <dt>Phone</dt><dd>@if($customer->phone)<a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a>@else — @endif {{ $customer->alt_phone }}</dd>
        <dt>Email</dt><dd>@if($customer->email)<a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>@else — @endif</dd>
        <dt>Address</dt><dd>{{ $customer->fullAddress() ?: '—' }}</dd>
        <dt>Tax #</dt><dd>{{ $customer->tax_number ?: '—' }}</dd><dt>License</dt><dd>{{ $customer->driver_license ?: '—' }}</dd>
        <dt>Since</dt><dd>{{ fmt_date($customer->created_at) }}</dd>
    </dl>@if($customer->notes)<div class="card-body border-top small"><i class="bi bi-sticky"></i> {{ $customer->notes }}</div>@endif</div></div>
    <div class="col-lg-8"><div class="row g-3">
        @can('invoices.view')
        <div class="col-md-4"><div class="card card-body"><div class="small text-secondary">Outstanding (customer)</div><div class="fs-4 fw-bold {{ $balance > 0 ? 'text-danger' : '' }}">{{ money($balance) }}</div></div></div>
        <div class="col-md-4"><div class="card card-body"><div class="small text-secondary">Awaiting lender</div><div class="fs-4 fw-bold">{{ money($lender) }}</div></div></div>
        <div class="col-md-4"><div class="card card-body"><div class="small text-secondary">Lifetime invoiced</div><div class="fs-4 fw-bold">{{ money($invoices->whereNotIn('status', ['draft', 'void'])->sum('total')) }}</div></div></div>
        @endcan
        <div class="col-12">@include('partials.attachments', ['model' => $customer, 'type' => 'customer', 'module' => 'customers'])</div>
    </div></div>
</div>
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#vehicles">Owned vehicles ({{ $vehicles->count() }})</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#purchases">Purchases ({{ $purchased->count() }})</a></li>
    @can('service_jobs.view')<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#repairs">Repair history ({{ $jobs->count() }})</a></li>@endcan
    @can('invoices.view')<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#invoices">Invoices ({{ $invoices->count() }})</a></li>@endcan
    @can('payments.view')<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#payments">Payments ({{ $payments->count() }})</a></li>@endcan
</ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="vehicles"><div class="card"><div class="list-group list-group-flush">
        @forelse($vehicles as $v)<a href="{{ route('vehicles.show', $v) }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span>{{ $v->title() }} <span class="small text-secondary font-monospace">{{ $v->vin }}</span></span><x-status :status="$v->inventory_status" /></a>
        @empty<div class="list-group-item text-secondary small">No customer-owned vehicles on file.</div>@endforelse
        @can('vehicles.create')<div class="list-group-item"><a href="{{ route('vehicles.create') }}" class="small">+ Add a customer vehicle</a></div>@endcan
    </div></div></div>
    <div class="tab-pane fade" id="purchases"><div class="card"><div class="list-group list-group-flush">
        @forelse($purchased as $v)<a href="{{ route('vehicles.show', $v) }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span>{{ $v->title() }} · {{ $v->stock_number }}</span><span class="small">{{ fmt_date($v->sold_at) }} · {{ money($v->sale_price) }}</span></a>
        @empty<div class="list-group-item text-secondary small">No vehicle purchases.</div>@endforelse</div></div></div>
    @can('service_jobs.view')<div class="tab-pane fade" id="repairs"><div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Job</th><th>Category</th><th>Vehicle</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>@forelse($jobs as $j)<tr><td><a href="{{ route('service-jobs.show', $j) }}">{{ $j->job_number }}</a> {{ $j->title }}</td><td>{{ $j->categoryLabel() }}</td><td>{{ $j->vehicle?->title() }}</td><td><x-status :status="$j->status" /></td><td>{{ fmt_date($j->created_at) }}</td></tr>
        @empty<tr><td colspan="5" class="text-secondary small">No repair history.</td></tr>@endforelse</tbody></table></div></div></div>@endcan
    @can('invoices.view')<div class="tab-pane fade" id="invoices"><div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Invoice</th><th>Date</th><th>Type</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Balance</th></tr></thead>
        <tbody>@forelse($invoices as $i)<tr><td><a href="{{ route('invoices.show', $i) }}">{{ $i->invoice_number }}</a></td><td>{{ fmt_date($i->invoice_date) }}</td><td>{{ config('autoshop.enums.invoice_types.'.$i->type) }}</td>
            <td><x-status :status="$i->status" /></td><td class="text-end money">{{ money($i->total) }}</td><td class="text-end money">{{ money($i->balance) }}</td></tr>
        @empty<tr><td colspan="6" class="text-secondary small">No invoices.</td></tr>@endforelse</tbody></table></div></div></div>@endcan
    @can('payments.view')<div class="tab-pane fade" id="payments"><div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Payment</th><th>Date</th><th>Type</th><th>Method</th><th>Status</th><th class="text-end">Amount</th></tr></thead>
        <tbody>@forelse($payments as $p)<tr><td><a href="{{ route('payments.show', $p) }}">{{ $p->payment_number }}</a></td><td>{{ fmt_date($p->payment_date) }}</td><td>{{ config('autoshop.enums.payment_types.'.$p->type) }}</td>
            <td>{{ config('autoshop.enums.payment_methods.'.$p->method) }}</td><td><x-status :status="$p->status" /></td><td class="text-end money">{{ money($p->signedAmount()) }}</td></tr>
        @empty<tr><td colspan="6" class="text-secondary small">No payments.</td></tr>@endforelse</tbody></table></div></div></div>@endcan
</div>
@endsection
