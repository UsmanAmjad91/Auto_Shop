@extends('layouts.app')
@section('title', 'Record payment')
@section('content')
@php $e = config('autoshop.enums'); @endphp
<x-page-header title="Record customer payment" :breadcrumbs="['Payments' => route('payments.index'), 'New' => null]" />
<form method="POST" action="{{ route('payments.store') }}" id="payment-form" data-invoice="{{ $invoiceId }}">@csrf
    <div class="row g-3">
        <div class="col-lg-4"><div class="card"><div class="card-body row g-3">
            <x-form.remote name="customer_id" type="customers" label="Customer" :value="$customer?->id" :text="$customer?->display_name" required col="col-12" id="customer_id" />
            <x-form.select name="type" label="Payment type" :options="['payment' => 'Payment', 'deposit' => 'Deposit / down payment']" col="col-12" />
            <x-form.input name="payment_date" type="date" label="Payment date" :value="today()" required col="col-6" :max="today()->toDateString()" />
            <x-form.input name="amount" type="number" step="0.01" min="0.01" label="Amount received" required col="col-6" />
            <x-form.select name="method" label="Method" :options="collect($e['payment_methods'])->except('financing')->all()" col="col-12" />
            <x-form.input name="reference" label="Reference" col="col-12" help="Cheque #, transfer ID, card last 4 / processor ID. Never enter full card numbers." />
            <div class="col-12 d-none" id="card-note"><div class="alert alert-warning small py-2 mb-0"><i class="bi bi-shield-lock"></i> For security, card numbers are not stored. Process the card on your terminal / gateway and record only its approval code or last 4 digits.</div></div>
            <x-form.textarea name="notes" label="Notes" rows="2" col="col-12" />
            <x-form.switch name="send_receipt" label="Email receipt to customer" :checked="true" col="col-12" />
            <div class="col-12 small text-secondary">Lender (financing) funds are recorded from the invoice's financing panel, not here.</div>
        </div></div></div>
        <div class="col-lg-8"><div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">Apply to open invoices <button type="button" class="btn btn-sm btn-outline-secondary" id="auto-allocate">Auto-allocate (oldest first)</button></div>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th class="text-end">Total</th><th class="text-end">Balance</th><th>Apply</th><th></th></tr></thead>
                <tbody id="allocations"></tbody>
                <tfoot><tr><td colspan="5" class="text-end fw-semibold">Allocated</td><td colspan="2" class="fw-bold" id="alloc-sum"></td></tr></tfoot>
            </table></div>
            <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Record payment</button></div>
        </div></div>
    </div>
</form>
@endsection
