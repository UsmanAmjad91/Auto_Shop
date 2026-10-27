<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $vehicle->title() }}</title>
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #111; margin: 0; padding: 24px; font-size: 13px; }
    .head { display: flex; justify-content: space-between; border-bottom: 3px solid {{ settings('theme.primary_color', '#1f6feb') }}; padding-bottom: 8px; margin-bottom: 14px; }
    h1 { font-size: 24px; margin: 0; } .price { font-size: 26px; font-weight: bold; color: {{ settings('theme.primary_color', '#1f6feb') }}; }
    table.specs { width: 100%; border-collapse: collapse; } table.specs td { padding: 5px 6px; border-bottom: 1px solid #eee; } table.specs td:first-child { color: #555; width: 35%; }
    .photo { width: 100%; max-height: 300px; object-fit: cover; border-radius: 6px; }
    .grid { width: 100%; } .grid td { vertical-align: top; }
    @media print { button { display: none; } }
</style></head>
<body>
@unless($pdf)<button onclick="window.print()">Print</button>@endunless
<table class="grid" style="margin-bottom:12px"><tr>
    <td><div class="muted">{{ settings('general.company_name') }} · {{ settings('general.company_phone') }}</div><h1>{{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}</h1><div>{{ $vehicle->trim }}</div></td>
    <td style="text-align:right">@if($vehicle->asking_price)<div class="price">{{ money($vehicle->asking_price) }}</div>@endif<div>Stock {{ $vehicle->stock_number }}</div></td>
</tr></table>
<table class="grid"><tr>
    <td style="width:55%;padding-right:14px">
        @php $img = $pdf ? ($image ?? null) : $vehicle->images->first()?->url(); @endphp
        @if($img)<img src="{{ $img }}" class="photo" alt="">@endif
        @if($vehicle->description)<p>{{ $vehicle->description }}</p>@endif
    </td>
    <td>
        <table class="specs">
            <tr><td>VIN</td><td style="font-family:monospace">{{ $vehicle->vin }}</td></tr>
            <tr><td>Mileage</td><td>{{ $vehicle->mileage !== null ? number_format($vehicle->mileage) : '—' }}</td></tr>
            <tr><td>Body</td><td>{{ $vehicle->body_type }}</td></tr>
            <tr><td>Engine</td><td>{{ $vehicle->engine ?: '—' }}</td></tr>
            <tr><td>Transmission</td><td>{{ $vehicle->transmission ?: '—' }}</td></tr>
            <tr><td>Drive</td><td>{{ $vehicle->drive_type ?: '—' }}</td></tr>
            <tr><td>Fuel</td><td>{{ $vehicle->fuel_type ?: '—' }}</td></tr>
            <tr><td>Exterior / Interior</td><td>{{ $vehicle->exterior_color ?: '—' }} / {{ $vehicle->interior_color ?: '—' }}</td></tr>
            <tr><td>Condition</td><td>{{ config('autoshop.enums.conditions.'.$vehicle->condition, '—') }}</td></tr>
            @foreach($customFields as $f)@if(isset($vehicle->custom_fields[$f->key]))<tr><td>{{ $f->label }}</td><td>{{ is_bool($vehicle->custom_fields[$f->key]) ? ($vehicle->custom_fields[$f->key] ? 'Yes' : 'No') : $vehicle->custom_fields[$f->key] }}</td></tr>@endif @endforeach
        </table>
        <div style="margin-top:12px">@if($pdf)<img src="{{ $qr }}" width="100" alt="">@else{!! $qr !!}@endif</div>
    </td>
</tr></table>
<p style="font-size:10px;color:#777;margin-top:20px">Information provided in good faith; please verify all details before purchase. Printed {{ now()->format('Y-m-d') }}.</p>
</body></html>
