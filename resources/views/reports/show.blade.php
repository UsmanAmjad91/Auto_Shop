@extends('layouts.app')
@section('title', $def['title'])
@section('content')
@php $f = $def['filters']; @endphp
<x-page-header :title="$def['title']" :breadcrumbs="['Reports' => route('reports.index'), $def['title'] => null]">
    @can('reports.export')
    <div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download"></i> Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            @foreach(['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF', 'print' => 'Print'] as $fmt => $l)
                <li><a class="dropdown-item" href="{{ route('reports.export', [$key] + request()->query() + ['format' => $fmt]) }}" @if($fmt === 'print') target="_blank" @endif>{{ $l }}</a></li>
            @endforeach
        </ul></div>
    @endcan
</x-page-header>
@if($f)
<form class="card card-body mb-3 filter-panel" method="GET"><div class="row g-2 align-items-end">
    @if(in_array('date', $f))
        <x-form.input name="date_from" type="date" label="From" :value="$result['from']->toDateString()" col="col-6 col-md-2" />
        <x-form.input name="date_to" type="date" label="To" :value="$result['to']->toDateString()" col="col-6 col-md-2" />
    @endif
    @if(in_array('period', $f))<x-form.select name="period" label="Group by" :options="['daily' => 'Day', 'weekly' => 'Week', 'monthly' => 'Month']" :value="request('period', 'monthly')" col="col-6 col-md-2" />@endif
    @if(in_array('vehicle', $f))<x-form.remote name="vehicle_id" type="vehicles" label="Vehicle" :value="request('vehicle_id')" :text="$selected['vehicle']?->title()" col="col-6 col-md-2" />@endif
    @if(in_array('customer', $f))<x-form.remote name="customer_id" type="customers" label="Customer" :value="request('customer_id')" :text="$selected['customer']?->display_name" col="col-6 col-md-2" />@endif
    @if(in_array('employee', $f))<x-form.remote name="employee_id" type="employees" label="Employee" :value="request('employee_id')" :text="$selected['employee']?->full_name" col="col-6 col-md-2" />@endif
    @if(in_array('department', $f))<x-form.select name="department_id" label="Department" :options="$departments" :value="request('department_id')" placeholder="All" col="col-6 col-md-2" />@endif
    <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button> <a href="{{ route('reports.show', $key) }}" class="btn btn-sm btn-light">Reset</a></div>
</div></form>
@endif
@if($result['summary'])
<div class="row g-3 mb-3">@foreach($result['summary'] as $label => $value)
    @if($label === 'Note')<div class="col-12"><div class="alert alert-light border small mb-0"><i class="bi bi-info-circle"></i> {{ $value }}</div></div>
    @else<div class="col-6 col-md-4 col-xl-3"><div class="card card-body h-100"><div class="small text-secondary">{{ $label }}</div><div class="fs-5 fw-bold">{{ $value }}</div></div></div>@endif
@endforeach</div>
@endif
@if($result['chart'] && count($result['chart']['labels']))
    <div class="card mb-3"><div class="card-body"><div class="chart-box"><canvas id="report-chart" data-chart='@json($result['chart'])' data-money="{{ in_array($key, ['repair_jobs']) ? 'false' : 'true' }}"></canvas></div></div></div>
@endif
<div class="card"><div class="table-responsive" style="max-height:70vh"><table class="table table-sm table-hover mb-0 align-middle">
    <thead class="sticky-top bg-body"><tr>@foreach($result['columns'] as $k => $label)<th class="{{ in_array($k, $result['money']) ? 'text-end' : '' }}">{{ $label }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse($result['rows'] as $row)
        <tr>@foreach($result['columns'] as $k => $label)<td class="{{ in_array($k, $result['money']) ? 'text-end money' : '' }} {{ in_array($k, $result['money']) && is_numeric($row[$k] ?? null) && $row[$k] < 0 ? 'text-loss' : '' }}">{{ in_array($k, $result['money']) && ($row[$k] ?? '') !== '' ? money($row[$k]) : ($row[$k] ?? '') }}</td>@endforeach</tr>
    @empty <tr><td colspan="{{ count($result['columns']) }}" class="empty-state">No data for the selected filters.</td></tr> @endforelse
    </tbody>
    @if(count($result['rows']) && $result['money'])
        <tfoot class="fw-bold"><tr>@foreach($result['columns'] as $k => $label)<td class="{{ in_array($k, $result['money']) ? 'text-end money' : '' }}">{{ $loop->first ? 'Totals' : (in_array($k, $result['money']) ? money(collect($result['rows'])->sum($k)) : '') }}</td>@endforeach</tr></tfoot>
    @endif
</table></div></div>
@endsection
