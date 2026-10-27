@extends('layouts.app')
@section('title', $payment->payment_number)
@section('content')
@php $u = auth()->user(); $e = config('autoshop.enums'); @endphp
<x-page-header :title="($payment->isRefund() ? 'Refund ' : 'Payment ').$payment->payment_number" :breadcrumbs="['Payments' => route('payments.index'), $payment->payment_number => null]">
    <a href="{{ route('payments.receipt', $payment) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Receipt</a>
    <a href="{{ route('payments.receipt', [$payment, 'pdf' => 1]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-pdf"></i></a>
    @if($payment->status === 'completed' && ! $payment->isRefund() && $u->can('payments.refund'))<button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#refundModal">Refund</button>@endif
    @if($payment->status === 'completed' && $u->can('payments.reverse'))<button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reverseModal">Reverse</button>@endif
</x-page-header>
@if($payment->status === 'reversed')<div class="alert alert-danger">Reversed {{ fmt_date($payment->reversed_at, true) }} — {{ $payment->reversal_reason }}</div>@endif
<div class="row g-3">
    <div class="col-lg-5"><div class="card"><dl class="dl-grid card-body">
        <dt>Customer</dt><dd><a href="{{ route('customers.show', $payment->customer_id) }}">{{ $payment->customer->display_name }}</a></dd>
        <dt>Type</dt><dd>{{ $e['payment_types'][$payment->type] ?? $payment->type }}</dd>
        <dt>Date</dt><dd>{{ fmt_date($payment->payment_date) }}</dd>
        <dt>Amount</dt><dd class="fs-4 fw-bold {{ $payment->isRefund() ? 'text-danger' : '' }}">{{ money($payment->signedAmount()) }}</dd>
        <dt>Method</dt><dd>{{ $e['payment_methods'][$payment->method] ?? $payment->method }}</dd>
        <dt>Reference</dt><dd>{{ $payment->reference ?: '—' }}</dd><dt>Status</dt><dd><x-status :status="$payment->status" /></dd>
        <dt>Received by</dt><dd>{{ $payment->receiver?->name }}</dd>
        @if($payment->refundOf)<dt>Refund of</dt><dd><a href="{{ route('payments.show', $payment->refundOf) }}">{{ $payment->refundOf->payment_number }}</a></dd>@endif
        @if($payment->notes)<dt>Notes</dt><dd>{{ $payment->notes }}</dd>@endif
    </dl></div></div>
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-header">Applied to</div><table class="table mb-0">
            <thead><tr><th>Invoice</th><th class="text-end">Applied</th><th class="text-end">Invoice balance now</th></tr></thead>
            <tbody>@foreach($payment->allocations as $a)<tr><td><a href="{{ route('invoices.show', $a->invoice_id) }}">{{ $a->invoice->invoice_number }}</a></td><td class="text-end money">{{ money($a->amount) }}</td><td class="text-end money">{{ money($a->invoice->balance) }}</td></tr>@endforeach</tbody></table></div>
        @if($payment->refunds->isNotEmpty())<div class="card"><div class="card-header">Refunds</div><ul class="list-group list-group-flush">
            @foreach($payment->refunds as $r)<li class="list-group-item d-flex justify-content-between"><a href="{{ route('payments.show', $r) }}">{{ $r->payment_number }}</a><span>{{ fmt_date($r->payment_date) }} · <span class="text-danger money">-{{ money($r->amount) }}</span> <x-status :status="$r->status" /></span></li>@endforeach</ul></div>@endif
    </div>
</div>
@push('modals')
<x-modal id="refundModal" title="Refund payment">
    <form method="POST" action="{{ route('payments.refund', $payment) }}">@csrf
        <x-form.select name="invoice_id" label="Invoice" :options="$payment->allocations->mapWithKeys(fn ($a) => [$a->invoice_id => $a->invoice->invoice_number.' (applied '.money($a->amount).')'])" />
        <div class="row g-2 mt-1"><x-form.input name="amount" type="number" step="0.01" min="0.01" label="Refund amount" required col="col-6" />
            <x-form.select name="method" label="Refund method" :options="collect($e['payment_methods'])->except('financing')->all()" :value="$payment->method" col="col-6" /></div>
        <div class="mt-2"><x-form.input name="reason" label="Reason" required /></div>
        <div class="text-end mt-3"><button class="btn btn-warning">Record refund</button></div>
    </form>
</x-modal>
<x-modal id="reverseModal" title="Reverse payment">
    <form method="POST" action="{{ route('payments.reverse', $payment) }}">@csrf
        <p class="small">Use reversal for payments recorded in error (e.g. bounced cheque). The record is kept, marked reversed, and invoice balances are restored.</p>
        <x-form.input name="reason" label="Reason" required />
        <div class="text-end mt-3"><button class="btn btn-danger">Reverse payment</button></div>
    </form>
</x-modal>
@endpush
@endsection
