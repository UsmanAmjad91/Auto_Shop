@extends('layouts.app')
@section('title', $po->po_number)
@section('content')
@php $u = auth()->user(); $open = in_array($po->status, ['draft', 'ordered', 'partially_received']); @endphp
<x-page-header :title="'Purchase order '.$po->po_number" :breadcrumbs="['Purchase orders' => route('purchase-orders.index'), $po->po_number => null]">
    @if($po->status === 'draft' && $u->can('purchase_orders.approve'))<a href="{{ route('purchase-orders.order', $po) }}" data-method="POST" class="btn btn-sm btn-primary">Mark as ordered</a>@endif
    @if(in_array($po->status, ['draft', 'ordered']) && $u->can('purchase_orders.edit'))<a href="{{ route('purchase-orders.edit', $po) }}" class="btn btn-sm btn-outline-primary">Edit</a>@endif
    @if($open && $u->can('purchase_orders.approve'))<a href="{{ route('purchase-orders.cancel', $po) }}" data-method="POST" data-confirm="Cancel this PO?" class="btn btn-sm btn-outline-danger">Cancel PO</a>@endif
    @if($po->status === 'draft' && $u->can('purchase_orders.delete'))<a href="{{ route('purchase-orders.destroy', $po) }}" data-method="DELETE" data-confirm="Delete draft?" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a>@endif
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
</x-page-header>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-4">
            <div><div class="small text-secondary">Supplier</div><a href="{{ route('suppliers.show', $po->supplier) }}">{{ $po->supplier->name }}</a></div>
            <div><div class="small text-secondary">Status</div><x-status :status="$po->status" /></div>
            <div><div class="small text-secondary">Ordered</div>{{ fmt_date($po->order_date) }}</div>
            <div><div class="small text-secondary">Expected</div>{{ fmt_date($po->expected_date) ?: '—' }}</div>
            <div><div class="small text-secondary">Receive into</div>{{ $po->location?->name }}</div>
            <div><div class="small text-secondary">Supplier inv.</div>{{ $po->supplier_invoice_number ?: '—' }}</div>
        </div></div>
        <form method="POST" action="{{ route('purchase-orders.receive', $po) }}" class="card">@csrf
            <div class="card-header">Items</div>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>Part</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Unit cost</th><th class="text-end">Total</th>@if($open && $u->can('purchase_orders.approve'))<th style="width:130px">Receive now</th>@endif</tr></thead>
                <tbody>@foreach($po->items as $i)<tr>
                    <td><a href="{{ route('parts.show', $i->part_id) }}">{{ $i->part->name }}</a><div class="small text-secondary">{{ $i->part->part_number }}</div></td>
                    <td class="text-end">{{ $i->quantity_ordered }}</td><td class="text-end">{{ $i->quantity_received }}</td><td class="text-end money">{{ money($i->unit_cost) }}</td><td class="text-end money">{{ money($i->line_total) }}</td>
                    @if($open && $u->can('purchase_orders.approve'))<td><input type="number" min="0" max="{{ $i->quantity_ordered - $i->quantity_received }}" name="receive[{{ $i->id }}]" value="{{ $i->quantity_ordered - $i->quantity_received }}" class="form-control form-control-sm" @disabled($i->quantity_received >= $i->quantity_ordered)></td>@endif
                </tr>@endforeach</tbody>
                <tfoot><tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end money">{{ money($po->subtotal) }}</td></tr>
                    <tr><td colspan="4" class="text-end">Tax</td><td class="text-end money">{{ money($po->tax_amount) }}</td></tr>
                    <tr><td colspan="4" class="text-end">Shipping</td><td class="text-end money">{{ money($po->shipping_cost) }}</td></tr>
                    <tr class="fw-bold"><td colspan="4" class="text-end">Total</td><td class="text-end money">{{ money($po->total) }}</td></tr></tfoot>
            </table></div>
            @if($open && $u->can('purchase_orders.approve'))
            <div class="card-footer row g-2 align-items-end">
                <x-form.input name="supplier_invoice_number" label="Supplier invoice #" :value="$po->supplier_invoice_number" col="col-md-4" />
                <x-form.input name="received_date" type="date" label="Received date" :value="today()" col="col-md-4" />
                <div class="col-md-4"><button class="btn btn-success w-100"><i class="bi bi-box-arrow-in-down"></i> Receive into stock</button></div>
            </div>
            @endif
        </form>
        @if($po->notes)<div class="card card-body mt-3 small">{{ $po->notes }}</div>@endif
    </div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between"><span>Total</span><strong class="money">{{ money($po->total) }}</strong></div>
            <div class="d-flex justify-content-between"><span>Paid</span><span class="money">{{ money($po->amount_paid) }}</span></div>
            <div class="d-flex justify-content-between fs-5"><span>Balance</span><strong class="money">{{ money($po->balance) }}</strong></div>
            <x-status :status="$po->payment_status" />
        </div></div>
        @if($u->can('purchase_orders.manage_payments') && $po->status !== 'cancelled' && $po->balance > 0)
        <form method="POST" action="{{ route('purchase-orders.pay', $po) }}" class="card mb-3">@csrf<div class="card-header">Record supplier payment</div><div class="card-body row g-2">
            <x-form.input name="amount" type="number" step="0.01" min="0.01" :max="$po->balance" label="Amount" :value="$po->balance" required col="col-6" />
            <x-form.input name="payment_date" type="date" label="Date" :value="today()" required col="col-6" />
            <x-form.select name="method" label="Method" :options="config('autoshop.enums.payment_methods')" col="col-6" />
            <x-form.input name="reference" label="Reference" col="col-6" />
            <div class="col-12"><button class="btn btn-primary w-100">Record payment</button></div></div></form>
        @endif
        <div class="card"><div class="card-header">Payments</div><ul class="list-group list-group-flush small">
            @forelse($po->payments as $p)<li class="list-group-item d-flex justify-content-between"><span>{{ fmt_date($p->payment_date) }} · {{ status_label($p->method) }} {{ $p->reference }}</span><span class="money">{{ money($p->amount) }}</span></li>
            @empty<li class="list-group-item text-secondary">No payments.</li>@endforelse</ul></div>
    </div>
</div>
@endsection
