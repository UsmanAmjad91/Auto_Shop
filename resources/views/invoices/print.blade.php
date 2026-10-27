<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $invoice->invoice_number }}</title>
<style>body{margin:0;padding:24px;background:#f3f4f6}.page{max-width:820px;margin:0 auto;background:#fff;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.bar{max-width:820px;margin:0 auto 12px;display:flex;gap:8px}@media print{body{padding:0;background:#fff}.page{box-shadow:none;padding:0}.bar{display:none}}
@media (max-width:600px){body{padding:8px}.page{padding:14px}}</style></head>
<body><div class="bar"><button onclick="window.print()">Print</button><a href="{{ route('invoices.pdf', [$invoice, 'download' => 1]) }}">Download PDF</a></div>
<div class="page">@include('invoices._document')</div></body></html>
