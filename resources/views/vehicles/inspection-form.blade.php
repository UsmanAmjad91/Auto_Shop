@extends('layouts.app')
@section('title', 'Vehicle inspection')
@section('content')
<x-page-header title="Inspection — {{ $vehicle->title() }}" :breadcrumbs="['Vehicles' => route('vehicles.index'), $vehicle->stock_number => route('vehicles.show', $vehicle), 'Inspection' => null]" />
<form method="POST" action="{{ route('vehicles.inspections.store', $vehicle) }}" class="card">@csrf
    <div class="card-body row g-3">
        <x-form.input name="inspection_date" type="date" label="Date" :value="today()" required col="col-md-3" />
        <x-form.select name="type" label="Type" :options="['intake' => 'Intake', 'pre_sale' => 'Pre-sale', 'quality' => 'Quality check', 'other' => 'Other']" col="col-md-3" />
        <x-form.remote name="inspector_id" type="employees" label="Inspector" :value="auth()->user()->employee?->id" :text="auth()->user()->employee?->full_name" col="col-md-6" />
    </div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Checkpoint</th><th class="text-center">Pass</th><th class="text-center">Fail</th><th class="text-center">N/A</th><th>Notes</th></tr></thead>
        <tbody>@foreach($items as $i => $item)<tr>
            <td><input type="hidden" name="items[{{ $i }}][name]" value="{{ $item }}">{{ $item }}</td>
            @foreach(['pass', 'fail', 'na'] as $st)<td class="text-center"><input class="form-check-input" type="radio" name="items[{{ $i }}][status]" value="{{ $st }}" @checked($st === 'pass') aria-label="{{ $item }} {{ $st }}"></td>@endforeach
            <td><input name="items[{{ $i }}][notes]" class="form-control form-control-sm"></td></tr>@endforeach</tbody>
    </table></div>
    <div class="card-body"><x-form.textarea name="notes" label="Overall notes" rows="3" /></div>
    <div class="card-footer text-end"><button class="btn btn-primary">Save inspection</button></div>
</form>
@endsection
