@extends('layouts.app')
@section('title', 'Import vehicles')
@section('content')
<x-page-header title="Bulk vehicle import" :breadcrumbs="['Vehicles' => route('vehicles.index'), 'Import' => null]" />
<div class="row g-3">
    <div class="col-lg-6"><form method="POST" action="{{ route('vehicles.import.store') }}" enctype="multipart/form-data" class="card">@csrf
        <div class="card-body">
            <x-form.input name="file" type="file" label="CSV file" accept=".csv,text/csv" required help="UTF-8 CSV with a header row. Max 5 MB." />
            <p class="small text-secondary mt-3 mb-0">Each row is validated (VIN format, duplicates, years, enums). Invalid rows are skipped and listed after import; valid rows are created in individual transactions.</p>
        </div>
        <div class="card-footer d-flex justify-content-between"><a href="{{ route('vehicles.import.template') }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Download template</a><button class="btn btn-primary">Import</button></div>
    </form></div>
    <div class="col-lg-6"><div class="card"><div class="card-header">Columns</div><div class="card-body small">
        <p>Required: <code>vin</code>, <code>make</code>, <code>model</code>, <code>year</code>.</p>
        <p>Optional: @foreach(array_diff($columns, ['vin', 'make', 'model', 'year']) as $c)<code>{{ $c }}</code> @endforeach</p>
        <p class="mb-0"><code>body_type</code>: {{ implode(', ', config('autoshop.enums.body_types')) }} · <code>ownership_type</code>: {{ implode(', ', array_keys(config('autoshop.enums.ownership_types'))) }} ·
            <code>condition</code>: {{ implode(', ', array_keys(config('autoshop.enums.conditions'))) }} · dates as YYYY-MM-DD.</p>
    </div></div></div>
</div>
@endsection
