@extends('layouts.app')
@section('title', 'Payments')
@section('content')
@php $e = config('autoshop.enums'); @endphp
<x-page-header title="Payments" :breadcrumbs="['Dashboard' => route('dashboard'), 'Payments' => null]">
    @can('payments.create')<a href="{{ route('payments.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Record payment</a>@endcan
</x-page-header>
<x-filters for="payments-table">
    <x-form.select name="type" label="Type" :options="$e['payment_types']" placeholder="All" col="col-6 col-md-2" />
    <x-form.select name="method" label="Method" :options="$e['payment_methods']" placeholder="All" col="col-6 col-md-2" />
    <x-form.select name="status" label="Status" :options="['completed' => 'Completed', 'reversed' => 'Reversed']" placeholder="Any" col="col-6 col-md-2" />
    <x-form.remote name="customer_id" type="customers" label="Customer" col="col-6 col-md-3" />
    <div class="col-12 col-md-3"><label class="form-label">Date</label><div class="input-group input-group-sm"><input type="date" name="date_from" class="form-control"><input type="date" name="date_to" class="form-control"></div></div>
</x-filters>
<x-datatable id="payments-table" :url="route('payments.index')" module="payments" sort="payment_date"
    :export-url="auth()->user()->can('payments.export') ? route('payments.export') : null" :columns="[
    ['key' => 'payment_number', 'label' => 'Payment #', 'type' => 'link', 'sortable' => true],
    ['key' => 'date', 'label' => 'Date', 'sortable' => true, 'sort' => 'payment_date'],
    ['key' => 'customer', 'label' => 'Customer'],
    ['key' => 'type', 'label' => 'Type'],
    ['key' => 'method', 'label' => 'Method'],
    ['key' => 'reference', 'label' => 'Reference', 'hidden' => true],
    ['key' => 'invoices', 'label' => 'Invoices'],
    ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'sortable' => true],
    ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ['key' => 'by', 'label' => 'Received by', 'hidden' => true],
    ['key' => 'actions', 'label' => '', 'type' => 'actions'],
]" />
@endsection
