@extends('layouts.app')
@section('title', 'Customers')
@section('content')
<x-page-header title="Customers" :breadcrumbs="['Dashboard' => route('dashboard'), 'Customers' => null]">
    @can('customers.create')<a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Add customer</a>@endcan
</x-page-header>
<x-filters for="customers-table">
    <x-form.select name="type" label="Type" :options="['individual' => 'Individual', 'business' => 'Business']" placeholder="All" col="col-6 col-md-2" />
    <x-form.select name="is_active" label="Status" :options="['1' => 'Active', '0' => 'Inactive']" placeholder="All" col="col-6 col-md-2" />
    <div class="col-6 col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="with_balance" value="1" id="with_balance"><label class="form-check-label small" for="with_balance">Has open balance</label></div></div>
    <div class="col-6 col-md-4"><label class="form-label">Created</label><div class="input-group input-group-sm"><input type="date" name="date_from" class="form-control"><input type="date" name="date_to" class="form-control"></div></div>
</x-filters>
@php $cb = array_values(array_filter([
    auth()->user()->can('customers.edit') ? ['action' => 'activate', 'label' => 'Mark active'] : null,
    auth()->user()->can('customers.edit') ? ['action' => 'deactivate', 'label' => 'Mark inactive'] : null,
    auth()->user()->can('customers.delete') ? ['action' => 'delete', 'label' => 'Delete (no invoices only)', 'confirm' => 'Delete :count customer(s)?', 'danger' => true] : null,
])); @endphp
<x-datatable id="customers-table" :url="route('customers.index')" module="customers" sort="created_at" :bulk="$cb" :bulk-url="route('customers.bulk')"
    :export-url="auth()->user()->can('customers.export') ? route('customers.export') : null" :columns="[
    ['key' => 'code', 'label' => 'Code', 'sortable' => true],
    ['key' => 'name', 'label' => 'Name', 'type' => 'link', 'sortable' => true],
    ['key' => 'type', 'label' => 'Type', 'hidden' => true],
    ['key' => 'phone', 'label' => 'Phone'],
    ['key' => 'email', 'label' => 'Email'],
    ['key' => 'city', 'label' => 'City', 'sortable' => true, 'hidden' => true],
    ['key' => 'vehicles', 'label' => 'Vehicles'],
    ['key' => 'invoices', 'label' => 'Invoices'],
    ['key' => 'balance', 'label' => 'Outstanding', 'type' => 'money'],
    ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ['key' => 'created_at', 'label' => 'Since', 'sortable' => true],
    ['key' => 'actions', 'label' => '', 'type' => 'actions'],
]" />
@endsection
