@extends('layouts.app')
@section('title', 'Parts Inventory')
@section('content')
@php
    $u = auth()->user(); $costs = $u->can('financials.view') || $u->can('parts.edit');
    $bulk = [];
    if ($u->can('parts.edit')) {
        $bulk[] = ['action' => 'category', 'label' => 'Set category', 'options' => $categories->all()];
        $bulk[] = ['action' => 'supplier', 'label' => 'Set supplier', 'options' => $suppliers->all()];
    }
    if ($u->can('parts.delete')) $bulk[] = ['action' => 'delete', 'label' => 'Delete selected (zero stock only)', 'confirm' => 'Delete :count part(s)?', 'danger' => true];
@endphp
<x-page-header title="Parts Inventory" :breadcrumbs="['Dashboard' => route('dashboard'), 'Parts' => null]">
    <a href="{{ route('parts.movements') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left-right"></i> Stock movements</a>
    @can('purchase_orders.create')<a href="{{ route('purchase-orders.create', ['low_stock' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-cart-plus"></i> Reorder low stock</a>@endcan
    @can('parts.import')<a href="{{ route('parts.import') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-upload"></i> Import</a>@endcan
    @can('parts.create')<a href="{{ route('parts.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add part</a>@endcan
</x-page-header>
<x-filters for="parts-table">
    <x-form.select name="stock_status" label="Stock status" :options="['available' => 'Available', 'low_stock' => 'Low / out of stock', 'out_of_stock' => 'Out of stock', 'reserved' => 'Has reservations', 'damaged' => 'Has damaged stock', 'returned' => 'Has returns']" placeholder="Any" col="col-6 col-md-3 col-xl-2" />
    <x-form.select name="category_id" label="Category" :options="$categories" placeholder="All" col="col-6 col-md-3 col-xl-2" />
    <x-form.select name="supplier_id" label="Supplier" :options="$suppliers" placeholder="All" col="col-6 col-md-3 col-xl-2" />
    <x-form.select name="location_id" label="Location" :options="$locations" placeholder="All" col="col-6 col-md-3 col-xl-2" />
    <x-form.select name="condition" label="Condition" :options="config('autoshop.enums.part_conditions')" placeholder="Any" col="col-6 col-md-3 col-xl-2" />
    <div class="col-12 col-md-6 col-xl-2"><label class="form-label">Fits vehicle (make / model / year)</label><div class="input-group input-group-sm">
        <input name="make" class="form-control" placeholder="Make"><input name="model" class="form-control" placeholder="Model"><input name="year" type="number" class="form-control" placeholder="Year"></div></div>
</x-filters>
<x-datatable id="parts-table" :url="route('parts.index')" module="parts" sort="name" dir="asc" :bulk="$bulk" :bulk-url="route('parts.bulk')"
    :export-url="$u->can('parts.export') || $u->can('parts.print') ? route('parts.export') : null"
    :columns="array_values(array_filter([
        ['key' => 'image', 'label' => '', 'type' => 'image'],
        ['key' => 'part_number', 'label' => 'Part ID', 'type' => 'link', 'sortable' => true],
        ['key' => 'name', 'label' => 'Name', 'sortable' => true],
        ['key' => 'oem_number', 'label' => 'OEM #'],
        ['key' => 'sku', 'label' => 'SKU', 'hidden' => true],
        ['key' => 'category', 'label' => 'Category'],
        ['key' => 'brand', 'label' => 'Brand', 'hidden' => true],
        ['key' => 'quantity', 'label' => 'On hand', 'sortable' => true],
        ['key' => 'reserved', 'label' => 'Reserved', 'hidden' => true],
        ['key' => 'available', 'label' => 'Available'],
        ['key' => 'min', 'label' => 'Min', 'hidden' => true],
        $costs ? ['key' => 'purchase_cost', 'label' => 'Cost', 'type' => 'money', 'sortable' => true] : null,
        ['key' => 'selling_price', 'label' => 'Price', 'type' => 'money', 'sortable' => true],
        $costs ? ['key' => 'value', 'label' => 'Stock value', 'type' => 'money', 'hidden' => true] : null,
        ['key' => 'location', 'label' => 'Location'],
        ['key' => 'supplier', 'label' => 'Supplier', 'hidden' => true],
        ['key' => 'stock_status', 'label' => 'Status', 'type' => 'badge', 'sortable' => true],
        ['key' => 'actions', 'label' => '', 'type' => 'actions'],
    ]))" />
@endsection
