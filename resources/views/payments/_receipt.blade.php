@php $e = config('autoshop.enums'); @endphp
<div style="font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;color:#111;max-width:620px;margin:0 auto">
    <table style="width:100%"><tr><td>@if($logo)<img src="{{ $logo }}" style="max-height:50px"><br>@endif<strong>{{ settings('general.company_name') }}</strong><br>
        <span style="color:#666">{{ settings('general.company_address') }}<br>{{ settings('general.company_phone') }}</span></td>
        <td style="text-align:right"><div style="font-size:20px;font-weight:bold">{{ $payment->isRefund() ? 'REFUND RECEIPT' : 'PAYMENT RECEIPT' }}</div>{{ $payment->payment_number }}<br>{{ fmt_date($payment->payment_date) }}</td></tr></table>
    <hr>
    <p>Received from <strong>{{ $payment->customer->display_name }}</strong></p>
    <table style="width:100%;border-collapse:collapse">
        <tr><td style="padding:4px 0">Amount</td><td style="text-align:right;font-size:18px;font-weight:bold">{{ money($payment->amount) }}</td></tr>
        <tr><td style="padding:4px 0">Method</td><td style="text-align:right">{{ $e['payment_methods'][$payment->method] ?? $payment->method }} {{ $payment->reference }}</td></tr>
        <tr><td style="padding:4px 0">Type</td><td style="text-align:right">{{ $e['payment_types'][$payment->type] ?? $payment->type }}</td></tr>
    </table>
    <table style="width:100%;border-collapse:collapse;margin-top:10px"><tr style="background:#f1f3f5"><th style="text-align:left;padding:6px">Invoice</th><th style="text-align:right;padding:6px">Applied</th><th style="text-align:right;padding:6px">Remaining balance</th></tr>
        @foreach($payment->allocations as $a)<tr><td style="padding:6px">{{ $a->invoice->invoice_number }}</td><td style="text-align:right;padding:6px">{{ money($a->amount) }}</td><td style="text-align:right;padding:6px">{{ money($a->invoice->balance) }}</td></tr>@endforeach</table>
    @if($payment->status === 'reversed')<p style="color:#dc2626;font-weight:bold">THIS PAYMENT WAS REVERSED on {{ fmt_date($payment->reversed_at) }}.</p>@endif
    <p style="color:#666;margin-top:24px">Received by {{ $payment->receiver?->name }}. Thank you for your business.</p>
</div>
