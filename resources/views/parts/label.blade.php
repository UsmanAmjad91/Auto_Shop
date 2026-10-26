<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Label {{ $part->part_number }}</title>
<style>body{font-family:Arial,sans-serif;padding:12px}.label{width:3in;border:1px dashed #999;padding:8px;display:flex;gap:8px;align-items:center}h1{font-size:15px;margin:0}.muted{font-size:11px;color:#555}@media print{.label{border:0}button{display:none}}</style>
</head><body><button onclick="window.print()" style="margin-bottom:10px">Print label</button>
<div class="label"><div>{!! $qr !!}</div><div><h1>{{ \Illuminate\Support\Str::limit($part->name, 40) }}</h1>
    <div class="muted">{{ $part->part_number }} · OEM {{ $part->oem_number ?: '—' }}</div>
    <div class="muted">{{ $part->defaultLocation?->name }} {{ $part->shelf }} {{ $part->bin }}</div><div>{!! $barcode !!}</div></div></div>
</body></html>
