<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Label {{ $vehicle->stock_number }}</title>
<style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 12px; }
    .label { width: 4in; border: 1px dashed #999; padding: 10px; display: flex; gap: 10px; align-items: center; }
    .label svg { display: block; }
    h1 { font-size: 20px; margin: 0; } .muted { color: #555; font-size: 12px; } .vin { font-family: monospace; font-size: 13px; letter-spacing: 1px; }
    @media print { .label { border: 0; } button { display: none; } }
</style></head>
<body>
<button onclick="window.print()" style="margin-bottom:10px">Print label</button>
<div class="label">
    <div>{!! $qr !!}</div>
    <div>
        <div class="muted">{{ settings('general.company_name') }}</div>
        <h1>{{ $vehicle->stock_number }}</h1>
        <div><strong>{{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}</strong> {{ $vehicle->trim }}</div>
        <div class="vin">{{ $vehicle->vin }}</div>
        <div>{!! $barcode !!}</div>
        @if($vehicle->asking_price)<div style="font-size:18px;font-weight:bold">{{ money($vehicle->asking_price) }}</div>@endif
    </div>
</div>
</body></html>
