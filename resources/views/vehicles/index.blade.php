@extends('layouts.app')
@section('title', 'Vehicles')
@section('content')
@php
    $u = auth()->user();
    $costs = $u->can('vehicles.view_costs');
    $enums = config('autoshop.enums');
    $bulk = [];
    if ($u->can('vehicles.change_status')) $bulk[] = ['action' => 'repair_status', 'label' => 'Set repair status', 'options' => $statuses];
    if ($u->can('vehicles.edit')) {
        $bulk[] = ['action' => 'inventory_status', 'label' => 'Set inventory status', 'options' => collect($enums['inventory_statuses'])->except(['sold'])->all()];
        $bulk[] = ['action' => 'assign', 'label' => 'Assign employee', 'options' => $employees->mapWithKeys(fn ($e) => [$e->id => $e->full_name])->all()];
    }
    if ($u->can('vehicles.delete')) $bulk[] = ['action' => 'delete', 'label' => 'Delete selected', 'confirm' => 'Delete :count vehicle(s)? Vehicles with invoices are skipped.', 'danger' => true];
@endphp
<x-page-header title="Vehicle Inventory" :breadcrumbs="['Dashboard' => route('dashboard'), 'Vehicles' => null]">
    @can('vehicles.import')<a href="{{ route('vehicles.import') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-upload"></i> Import</a>@endcan
    @can('vehicles.create')<a href="{{ route('vehicles.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add vehicle</a>@endcan
</x-page-header>

<x-filters for="vehicles-table">
    <x-form.input name="vin" label="VIN" col="col-6 col-md-3 col-xl-2" placeholder="Full or partial" />
    <x-form.input name="stock_number" label="Stock #" col="col-6 col-md-3 col-xl-2" />
    <x-form.select name="make" label="Make" :options="$makes->combine($makes)" placeholder="All makes" col="col-6 col-md-3 col-xl-2" />
    <x-form.input name="model" label="Model" col="col-6 col-md-3 col-xl-2" />
    <x-form.select name="body_type" label="Type" :options="array_combine($enums['body_types'], $enums['body_types'])" placeholder="All" col="col-6 col-md-3 col-xl-2" />
    <x-form.select name="repair_status" label="Repair status" :options="$statuses" placeholder="Any" col="col-6 col-md-3 col-xl-2" />
    <x-form.select name="inventory_status" label="Inventory status" :options="$enums['inventory_statuses']" placeholder="Active (not archived)" col="col-6 col-md-3 col-xl-2" />
    <x-form.select name="payment_status" label="Payment status" :options="$enums['vehicle_payment_statuses']" placeholder="Any" col="col-6 col-md-3 col-xl-2" />
    <x-form.select name="ownership_type" label="Ownership" :options="$enums['ownership_types']" placeholder="Any" col="col-6 col-md-3 col-xl-2" />
    <x-form.select name="assigned_employee_id" label="Assigned to" :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->full_name])" placeholder="Anyone" col="col-6 col-md-3 col-xl-2" />
    <div class="col-6 col-md-3 col-xl-2"><label class="form-label">Year</label><div class="input-group input-group-sm">
        <input type="number" name="year_from" class="form-control" placeholder="From" min="1900"><input type="number" name="year_to" class="form-control" placeholder="To" min="1900"></div></div>
    @if($costs)<div class="col-6 col-md-3 col-xl-2"><label class="form-label">Purchase price</label><div class="input-group input-group-sm">
        <input type="number" name="purchase_min" class="form-control" placeholder="Min"><input type="number" name="purchase_max" class="form-control" placeholder="Max"></div></div>@endif
    <div class="col-6 col-md-3 col-xl-2"><label class="form-label">Asking price</label><div class="input-group input-group-sm">
        <input type="number" name="asking_min" class="form-control" placeholder="Min"><input type="number" name="asking_max" class="form-control" placeholder="Max"></div></div>
    <div class="col-6 col-md-3 col-xl-2"><label class="form-label">Date added</label><div class="input-group input-group-sm">
        <input type="date" name="date_from" class="form-control" aria-label="From"><input type="date" name="date_to" class="form-control" aria-label="To"></div></div>
    <div class="col-12 col-md-3 col-xl-2"><label class="form-label">Quick sort</label>
        <select class="form-select" onchange="const t=document.getElementById('vehicles-table').datatable;const[s,d]=this.value.split(':');if(s){t.state.sort=s;t.state.dir=d;t.load();}">
            <option value="">—</option><option value="asking_price:asc">Price: low → high</option><option value="asking_price:desc">Price: high → low</option>
            <option value="created_at:desc">Newest first</option><option value="created_at:asc">Oldest first</option><option value="year:desc">Year: newest</option><option value="year:asc">Year: oldest</option>
        </select></div>
</x-filters>

<x-datatable id="vehicles-table" :url="route('vehicles.index')" module="vehicles" sort="created_at" cards
    :export-url="$u->can('vehicles.export') || $u->can('vehicles.print') ? route('vehicles.export') : null"
    :bulk="$bulk" :bulk-url="route('vehicles.bulk')"
    :columns="array_values(array_filter([
        ['key' => 'id', 'label' => 'ID', 'sortable' => true, 'hidden' => true],
        ['key' => 'image', 'label' => 'Photo', 'type' => 'image'],
        ['key' => 'stock_number', 'label' => 'Stock #', 'type' => 'link', 'sortable' => true, 'card' => true],
        ['key' => 'vin', 'label' => 'VIN', 'type' => 'mono', 'sortable' => true, 'card' => true],
        ['key' => 'make', 'label' => 'Make', 'sortable' => true],
        ['key' => 'model', 'label' => 'Model', 'sortable' => true],
        ['key' => 'year', 'label' => 'Year', 'sortable' => true],
        ['key' => 'trim', 'label' => 'Trim', 'hidden' => true],
        ['key' => 'body_type', 'label' => 'Type', 'hidden' => true],
        ['key' => 'mileage', 'label' => 'Mileage', 'sortable' => true, 'card' => true],
        $costs ? ['key' => 'purchase_price', 'label' => 'Purchase', 'type' => 'money', 'sortable' => true] : null,
        $costs ? ['key' => 'repair_cost', 'label' => 'Repair cost', 'type' => 'money'] : null,
        $costs ? ['key' => 'total_investment', 'label' => 'Investment', 'type' => 'money', 'sortable' => true] : null,
        ['key' => 'asking_price', 'label' => 'Asking', 'type' => 'money', 'sortable' => true, 'card' => true],
        ['key' => 'sale_price', 'label' => 'Sale price', 'type' => 'money', 'sortable' => true, 'hidden' => true],
        $costs ? ['key' => 'profit', 'label' => 'Profit / Loss', 'type' => 'profit'] : null,
        ['key' => 'repair_status', 'label' => 'Repair', 'type' => 'badge', 'sortable' => true, 'card' => true],
        ['key' => 'payment_status', 'label' => 'Payment', 'type' => 'badge'],
        ['key' => 'inventory_status', 'label' => 'Inventory', 'type' => 'badge', 'sortable' => true, 'card' => true],
        ['key' => 'ownership', 'label' => 'Ownership', 'hidden' => true],
        ['key' => 'created_at', 'label' => 'Added', 'sortable' => true],
        ['key' => 'assigned', 'label' => 'Assigned', 'hidden' => true],
        ['key' => 'actions', 'label' => '', 'type' => 'actions'],
    ]))" />
@endsection
