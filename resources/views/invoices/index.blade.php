@extends('layouts.app')
@section('title', 'Sales & Invoices')
@section('content')
@php $u = auth()->user(); $e = config('autoshop.enums'); @endphp
<x-page-header title="Sales & Invoices" :breadcrumbs="['Dashboard' => route('dashboard'), 'Invoices' => null]">
    @can('invoices.create')
        <a href="{{ route('invoices.create', ['type' => 'vehicle_sale']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bag-check"></i> Vehicle sale</a>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New invoice</a>
    @endcan
</x-page-header>
<x-filters for="invoices-table">
    <x-form.select name="type" label="Type" :options="$e['invoice_types']" placeholder="All" col="col-6 col-md-2" />
    <x-form.select name="status" label="Status" :options="$e['invoice_statuses']" placeholder="Any" col="col-6 col-md-2" />
    <x-form.select name="payment_status" label="Payment" :options="['unpaid' => 'Unpaid', 'partial' => 'Partially paid', 'paid' => 'Paid', 'pending' => 'Pending (lender)', 'refunded' => 'Refunded']" placeholder="Any" col="col-6 col-md-2" />
    <x-form.remote name="customer_id" type="customers" label="Customer" col="col-6 col-md-2" />
    <x-form.remote name="sales_rep_id" type="users" label="Sales rep" col="col-6 col-md-2" />
    <div class="col-6 col-md-2"><label class="form-label">Total</label><div class="input-group input-group-sm"><input type="number" name="min_total" class="form-control" placeholder="Min"><input type="number" name="max_total" class="form-control" placeholder="Max"></div></div>
    <div class="col-6 col-md-3"><label class="form-label">Invoice date</label><div class="input-group input-group-sm"><input type="date" name="date_from" class="form-control"><input type="date" name="date_to" class="form-control"></div></div>
</x-filters>
<x-datatable id="invoices-table" :url="route('invoices.index')" module="invoices" sort="invoice_date" :bulk-url="route('invoices.bulk')"
    :bulk="$u->can('invoices.email') ? [['action' => 'remind', 'label' => 'Email payment reminders', 'confirm' => 'Send reminders for :count invoice(s)? Only open invoices with an email are sent.']] : []"
    :export-url="$u->can('invoices.export') ? route('invoices.export') : null" :columns="[
    ['key' => 'invoice_number', 'label' => 'Invoice #', 'type' => 'link', 'sortable' => true],
    ['key' => 'invoice_date', 'label' => 'Date', 'sortable' => true],
    ['key' => 'due_date', 'label' => 'Due', 'sortable' => true],
    ['key' => 'type', 'label' => 'Type'],
    ['key' => 'customer', 'label' => 'Customer', 'sortable' => true],
    ['key' => 'vehicle', 'label' => 'Vehicle', 'hidden' => true],
    ['key' => 'total', 'label' => 'Total', 'type' => 'money', 'sortable' => true],
    ['key' => 'paid', 'label' => 'Paid', 'type' => 'money'],
    ['key' => 'balance', 'label' => 'Balance', 'type' => 'money', 'sortable' => true],
    ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ['key' => 'payment_status', 'label' => 'Payment', 'type' => 'badge'],
    ['key' => 'rep', 'label' => 'Sales rep', 'hidden' => true],
    ['key' => 'actions', 'label' => '', 'type' => 'actions'],
]" />
@endsection
