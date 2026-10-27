@extends('layouts.app')
@section('title', 'Import parts')
@section('content')
<x-page-header title="Bulk parts import" :breadcrumbs="['Parts' => route('parts.index'), 'Import' => null]" />
<div class="row g-3">
    <div class="col-lg-6"><form method="POST" action="{{ route('parts.import.store') }}" enctype="multipart/form-data" class="card">@csrf
        <div class="card-body"><x-form.input name="file" type="file" label="CSV file" accept=".csv,text/csv" required />
            <p class="small text-secondary mt-3 mb-0">Rows matching an existing SKU (or Part ID) update that part's details. New parts get their opening quantity as a stock movement. Existing quantities are never overwritten by imports.</p></div>
        <div class="card-footer d-flex justify-content-between"><a href="{{ route('parts.import.template') }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Template</a><button class="btn btn-primary">Import</button></div>
    </form></div>
    <div class="col-lg-6"><div class="card card-body small">Columns: @foreach($columns as $c)<code>{{ $c }}</code> @endforeach
        <p class="mt-2 mb-0">Only <code>name</code> is required. <code>category</code> is created if missing; <code>supplier</code> must match an existing supplier name.</p></div></div>
</div>
@endsection
