@extends('layouts.app')
@section('title', 'Suppliers')
@section('content')
<x-page-header title="Suppliers" :breadcrumbs="['Dashboard' => route('dashboard'), 'Suppliers' => null]">
    @can('suppliers.create')<a href="{{ route('suppliers.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add supplier</a>@endcan
</x-page-header>
<x-filters for="suppliers-table"><x-form.select name="is_active" label="Status" :options="['1' => 'Active', '0' => 'Inactive']" placeholder="All" col="col-6 col-md-3" /></x-filters>
<x-datatable id="suppliers-table" :url="route('suppliers.index')" module="suppliers" sort="name" dir="asc" :bulk-url="route('suppliers.bulk')"
    :bulk="auth()->user()->can('suppliers.edit') ? [['action' => 'activate', 'label' => 'Mark active'], ['action' => 'deactivate', 'label' => 'Mark inactive']] : []"
    :export-url="auth()->user()->can('suppliers.export') ? route('suppliers.export') : null" :columns="[
    ['key' => 'code', 'label' => 'Code', 'sortable' => true],
    ['key' => 'name', 'label' => 'Name', 'type' => 'link', 'sortable' => true],
    ['key' => 'contact', 'label' => 'Contact'],
    ['key' => 'phone', 'label' => 'Phone'],
    ['key' => 'email', 'label' => 'Email'],
    ['key' => 'city', 'label' => 'City', 'hidden' => true],
    ['key' => 'parts', 'label' => 'Parts'],
    ['key' => 'pending', 'label' => 'Amount pending', 'type' => 'money'],
    ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ['key' => 'actions', 'label' => '', 'type' => 'actions'],
]" />
@endsection
