<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Receipt {{ $payment->payment_number }}</title>
<style>body{padding:20px}@media print{button{display:none}}</style></head>
<body><button onclick="window.print()">Print</button><a href="{{ route('payments.receipt', [$payment, 'pdf' => 1]) }}" style="margin-left:8px">PDF</a>
@include('payments._receipt')</body></html>
