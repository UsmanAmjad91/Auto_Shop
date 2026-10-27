@extends('layouts.app')
@php $title = $category ? config('autoshop.enums.service_categories.'.$category) : 'All Service Jobs'; @endphp
@section('title', $title)
@section('content')
@php $u = auth()->user(); $fin = $u->can('financials.view') || $u->can('invoices.view'); @endphp
<x-page-header :title="$title" :breadcrumbs="['Dashboard' => route('dashboard'), $title => null]">
    @can('service_jobs.create')<a href="{{ route('service-jobs.create', ['category' => $category ?? 'repair']) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New job</a>@endcan
</x-page-header>
<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link {{ ! $category ? 'active' : '' }}" href="{{ route('service-jobs.index') }}">All</a></li>
    @foreach(config('autoshop.enums.service_categories') as $k => $l)<li class="nav-item"><a class="nav-link {{ $category === $k ? 'active' : '' }}" href="{{ route('service-jobs.index', ['category' => $k]) }}">{{ $l }}</a></li>@endforeach
</ul>
<x-filters for="jobs-table">
    @if($category)<input type="hidden" name="category" value="{{ $category }}">@else
        <x-form.select name="category" label="Category" :options="config('autoshop.enums.service_categories')" placeholder="All" col="col-6 col-md-2" />@endif
    <x-form.select name="status" label="Status" :options="config('autoshop.enums.service_statuses')" placeholder="Any" col="col-6 col-md-2" />
    <x-form.select name="service_type_id" label="Service type" :options="$serviceTypes" placeholder="Any" col="col-6 col-md-2" />
    <x-form.select name="priority" label="Priority" :options="config('autoshop.enums.priorities')" placeholder="Any" col="col-6 col-md-2" />
    <x-form.remote name="employee_id" type="employees" label="Employee" col="col-6 col-md-2" />
    <x-form.remote name="vehicle_id" type="vehicles" label="Vehicle" col="col-6 col-md-2" />
    <x-form.remote name="customer_id" type="customers" label="Customer" col="col-6 col-md-2" />
    <div class="col-6 col-md-3"><label class="form-label">Created</label><div class="input-group input-group-sm"><input type="date" name="date_from" class="form-control"><input type="date" name="date_to" class="form-control"></div></div>
    <div class="col-6 col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="unbilled" value="1" id="unbilled"><label class="form-check-label small" for="unbilled">Billable, not invoiced</label></div></div>
</x-filters>
<x-datatable id="jobs-table" :url="route('service-jobs.index')" module="service_jobs" sort="created_at" :bulk-url="route('service-jobs.bulk')"
    :bulk="$u->can('service_jobs.edit') ? [['action' => 'status', 'label' => 'Change status', 'options' => collect(config('autoshop.enums.service_statuses'))->when(! $u->can('service_jobs.approve'), fn ($c) => $c->except(['quality_checked', 'delivered', 'cancelled']))->all()]] : []"
    :export-url="$u->can('service_jobs.export') ? route('service-jobs.export') : null"
    :columns="array_values(array_filter([
        ['key' => 'job_number', 'label' => 'Job #', 'type' => 'link', 'sortable' => true],
        $category ? null : ['key' => 'category', 'label' => 'Category'],
        ['key' => 'title', 'label' => 'Title'],
        ['key' => 'vehicle', 'label' => 'Vehicle'],
        ['key' => 'vin', 'label' => 'VIN', 'type' => 'mono', 'hidden' => true],
        ['key' => 'customer', 'label' => 'Customer'],
        ['key' => 'employees', 'label' => 'Assigned'],
        ['key' => 'supervisor', 'label' => 'Supervisor', 'hidden' => true],
        ['key' => 'scheduled', 'label' => 'Scheduled', 'sortable' => true, 'sort' => 'scheduled_date'],
        ['key' => 'priority', 'label' => 'Priority', 'type' => 'badge'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'sortable' => true],
        $fin ? ['key' => 'total_cost', 'label' => 'Cost', 'type' => 'money', 'sortable' => true] : null,
        $fin ? ['key' => 'customer_charge', 'label' => 'Charge', 'type' => 'money', 'sortable' => true] : null,
        ['key' => 'invoiced', 'label' => 'Invoiced', 'hidden' => true],
        ['key' => 'created_at', 'label' => 'Created', 'sortable' => true, 'hidden' => true],
        ['key' => 'actions', 'label' => '', 'type' => 'actions'],
    ]))" />
@endsection
