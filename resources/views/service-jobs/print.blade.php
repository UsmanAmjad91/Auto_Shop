<!DOCTYPE html><html><head><meta charset="utf-8"><title>Job card {{ $job->job_number }}</title>
<style>body{font-family:Arial,sans-serif;font-size:13px;padding:20px;color:#111}h1{font-size:20px;margin:0}table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{border:1px solid #ccc;padding:6px;text-align:left}.grid td{border:0;padding:2px 6px}.sig{margin-top:40px;display:flex;gap:40px}.sig div{flex:1;border-top:1px solid #000;padding-top:4px}@media print{button{display:none}}</style></head>
<body><button onclick="window.print()">Print</button>
<table class="grid"><tr><td><strong>{{ settings('general.company_name') }}</strong><br>{{ settings('general.company_address') }}<br>{{ settings('general.company_phone') }}</td>
    <td style="text-align:right"><h1>JOB CARD {{ $job->job_number }}</h1>{{ $job->categoryLabel() }} · {{ status_label($job->status) }}<br>Created {{ fmt_date($job->created_at) }}</td></tr></table>
<table class="grid" style="margin-top:12px">
    <tr><td><strong>Vehicle:</strong> {{ $job->vehicle->title() }}</td><td><strong>VIN:</strong> {{ $job->vehicle->vin }}</td></tr>
    <tr><td><strong>Stock / plate:</strong> {{ $job->vehicle->stock_number }} / {{ $job->vehicle->license_plate }}</td><td><strong>Odometer:</strong> {{ $job->odometer_in ? number_format($job->odometer_in) : '—' }}</td></tr>
    <tr><td><strong>Customer:</strong> {{ $job->customer?->display_name ?? 'Internal' }} {{ $job->customer?->phone }}</td><td><strong>Supervisor:</strong> {{ $job->supervisor?->full_name }}</td></tr>
    <tr><td colspan="2"><strong>Technicians:</strong> {{ $job->employees->map(fn ($e) => $e->full_name.' ('.$e->pivot->role.')')->implode(', ') }}</td></tr>
</table>
<p><strong>{{ $job->title }}</strong><br>{{ $job->description }}</p>
<table><thead><tr><th>Type</th><th>Description</th><th>Qty</th></tr></thead><tbody>
    @forelse($job->items as $i)<tr><td>{{ ucfirst($i->item_type) }}</td><td>{{ $i->description }}</td><td>{{ rtrim(rtrim((string) $i->quantity, '0'), '.') }}</td></tr>@empty<tr><td colspan="3">&nbsp;</td></tr><tr><td colspan="3">&nbsp;</td></tr>@endforelse
</tbody></table>
<table><thead><tr><th style="width:40px">✓</th><th>Checklist</th></tr></thead><tbody>
    @foreach($job->checklist ?? [] as $c)<tr><td>{{ $c['done'] ? '✔' : '' }}</td><td>{{ $c['item'] }}</td></tr>@endforeach</tbody></table>
<p><strong>Notes:</strong> {{ $job->notes }}</p>
<div class="sig"><div>Technician signature</div><div>Quality check</div><div>Customer signature</div></div>
</body></html>
