@extends('layouts.app')
@section('title', 'Stock Movements')
@section('content')
<x-page-header title="Stock Movement History" :breadcrumbs="['Parts' => route('parts.index'), 'Movements' => null]" />
<x-filters for="movements-table">
    <x-form.select name="type" label="Type" :options="$types" placeholder="All" col="col-6 col-md-3" />
    <x-form.select name="location_id" label="Location" :options="$locations" placeholder="All" col="col-6 col-md-3" />
    <x-form.remote name="part_id" type="parts" label="Part" col="col-6 col-md-3" />
    <div class="col-6 col-md-3"><label class="form-label">Date range</label><div class="input-group input-group-sm"><input type="date" name="date_from" class="form-control"><input type="date" name="date_to" class="form-control"></div></div>
</x-filters>
<x-datatable id="movements-table" :url="route('parts.movements')" module="movements" sort="created_at" :columns="[
    ['key' => 'date', 'label' => 'Date', 'sortable' => true, 'sort' => 'created_at'],
    ['key' => 'part_number', 'label' => 'Part ID', 'type' => 'link'],
    ['key' => 'part', 'label' => 'Part'],
    ['key' => 'type', 'label' => 'Movement', 'type' => 'badge'],
    ['key' => 'quantity', 'label' => 'Qty', 'sortable' => true],
    ['key' => 'reserved', 'label' => 'Reserved Δ'],
    ['key' => 'balance', 'label' => 'Balance'],
    ['key' => 'location', 'label' => 'Location'],
    ['key' => 'vehicle', 'label' => 'Vehicle'],
    ['key' => 'user', 'label' => 'User'],
    ['key' => 'notes', 'label' => 'Notes'],
]" />
@endsection
