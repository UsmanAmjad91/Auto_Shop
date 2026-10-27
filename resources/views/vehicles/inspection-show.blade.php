@extends('layouts.app')
@section('title', 'Inspection')
@section('content')
<x-page-header title="Inspection {{ fmt_date($inspection->inspection_date) }}" :breadcrumbs="['Vehicles' => route('vehicles.index'), $vehicle->stock_number => route('vehicles.show', $vehicle), 'Inspection' => null]">
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
</x-page-header>
<div class="card">
    <div class="card-body d-flex flex-wrap gap-4">
        <div><div class="small text-secondary">Vehicle</div>{{ $vehicle->title() }} · {{ $vehicle->vin }}</div>
        <div><div class="small text-secondary">Type</div>{{ status_label($inspection->type) }}</div>
        <div><div class="small text-secondary">Inspector</div>{{ $inspection->inspector?->full_name ?? '—' }}</div>
        <div><div class="small text-secondary">Result</div><x-status :status="$inspection->result" /></div>
    </div>
    <table class="table mb-0"><thead><tr><th>Checkpoint</th><th>Result</th><th>Notes</th></tr></thead><tbody>
        @foreach($inspection->items as $item)<tr><td>{{ $item['name'] }}</td><td><x-status :status="$item['status']" :label="strtoupper($item['status'])" /></td><td class="small">{{ $item['notes'] ?? '' }}</td></tr>@endforeach
    </tbody></table>
    @if($inspection->notes)<div class="card-body border-top small">{{ $inspection->notes }}</div>@endif
</div>
@endsection
