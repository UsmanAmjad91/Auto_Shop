<!DOCTYPE html><html><head><meta charset="utf-8"><title>{{ $title }}</title>
<style>body{font-family:DejaVu Sans,Arial,sans-serif;font-size:9.5px;color:#111;margin:{{ isset($print) ? '16px' : '0' }}}h1{font-size:15px;margin:0 0 4px}.muted{color:#666}
table{width:100%;border-collapse:collapse;margin-top:8px}th{background:#f1f3f5;text-align:left;padding:5px;border-bottom:1px solid #ccc}td{padding:4px 5px;border-bottom:1px solid #eee}
.sum{margin-top:8px}@media print{.noprint{display:none}}</style></head>
<body>@isset($print)<button class="noprint" onclick="window.print()">Print</button>@endisset
<h1>{{ settings('general.company_name') }} — {{ $title }}</h1><div class="muted">Generated {{ now()->format('Y-m-d H:i') }} by {{ auth()->user()?->name }} · {{ count($data) }} rows</div>
@if(! empty($summary))<div class="sum">@foreach($summary as $k => $v)<div><strong>{{ $k }}:</strong> {{ $v }}</div>@endforeach</div>@endif
<table><thead><tr>@foreach($headings as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
<tbody>@foreach($data as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody></table>
@isset($print)<script>window.addEventListener('load', () => window.print());</script>@endisset
</body></html>
