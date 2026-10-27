{{-- Shared invoice document body for PDF and print. Template from Settings > General. --}}
@php
    $e = config('autoshop.enums');
    $tpl = settings('general.invoice_template', 'classic');
    $accent = settings('theme.primary_color', '#1f6feb');
@endphp
<style>
    .inv { font-family: DejaVu Sans, Arial, sans-serif; font-size: {{ $tpl === 'compact' ? '10px' : '11.5px' }}; color: #111; }
    .inv table { width: 100%; border-collapse: collapse; }
    .inv .head td { vertical-align: top; }
    .inv h1 { font-size: 26px; margin: 0; color: {{ $tpl === 'modern' ? $accent : '#111' }}; letter-spacing: 1px; }
    .inv .muted { color: #666; }
    .inv .items th { background: {{ $tpl === 'modern' ? $accent : '#f1f3f5' }}; color: {{ $tpl === 'modern' ? '#fff' : '#111' }}; text-align: left; padding: 7px; font-size: 10.5px; text-transform: uppercase; }
    .inv .items td { padding: 7px; border-bottom: 1px solid #e5e7eb; }
    .inv .r { text-align: right; }
    .inv .totals td { padding: 4px 7px; } .inv .totals .grand td { font-size: 15px; font-weight: bold; border-top: 2px solid #111; }
    .inv .stamp { border: 3px solid #dc2626; color: #dc2626; padding: 4px 14px; font-size: 22px; font-weight: bold; display: inline-block; transform: rotate(-8deg); }
    .inv .paid { border-color: #059669; color: #059669; }
    .inv .box { border: 1px solid #e5e7eb; padding: 8px; border-radius: 4px; }
</style>
<div class="inv">
<table class="head"><tr>
    <td style="width:55%">
        @if($logo)<img src="{{ $logo }}" style="max-height:60px;margin-bottom:6px" alt=""><br>@endif
        <strong style="font-size:15px">{{ settings('general.company_name') }}</strong><br>
        <span class="muted">{!! nl2br(e(settings('general.company_address'))) !!}<br>{{ settings('general.company_phone') }} {{ settings('general.company_email') }}<br>{{ settings('general.website') }}</span>
    </td>
    <td class="r">
        <h1>INVOICE</h1>
        <table style="width:auto;margin-left:auto"><tr><td class="muted">Invoice #</td><td class="r"><strong>{{ $invoice->invoice_number }}</strong></td></tr>
            <tr><td class="muted">Date</td><td class="r">{{ fmt_date($invoice->invoice_date) }}</td></tr>
            <tr><td class="muted">Due</td><td class="r">{{ fmt_date($invoice->due_date) ?: 'On receipt' }}</td></tr>
            <tr><td class="muted">Type</td><td class="r">{{ $e['invoice_types'][$invoice->type] }}</td></tr></table>
        @if($invoice->isVoid())<div class="stamp" style="margin-top:8px">VOID</div>@elseif($invoice->payment_status === 'paid')<div class="stamp paid" style="margin-top:8px">PAID</div>@endif
    </td>
</tr></table>
<br>
<table><tr>
    <td style="width:50%;vertical-align:top" class="box"><div class="muted">BILL TO</div><strong>{{ $invoice->customer_name }}</strong><br>{{ $invoice->customer_address }}<br>{{ $invoice->customer_email }} {{ $invoice->customer_phone }}</td>
    <td style="width:2%"></td>
    @if($invoice->vehicle)<td style="vertical-align:top" class="box"><div class="muted">VEHICLE</div><strong>{{ $invoice->vehicle->year }} {{ $invoice->vehicle->make }} {{ $invoice->vehicle->model }}</strong> {{ $invoice->vehicle->trim }}<br>
        VIN: {{ $invoice->vehicle->vin }}<br>Stock: {{ $invoice->vehicle->stock_number }} @if($invoice->vehicle->mileage)· {{ number_format($invoice->vehicle->mileage) }} mi @endif</td>@else<td></td>@endif
</tr></table>
<br>
<table class="items"><thead><tr><th>Description</th><th>Type</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Amount</th></tr></thead><tbody>
    @foreach($invoice->items as $i)<tr><td>{{ $i->description }}</td><td>{{ $e['invoice_item_types'][$i->item_type] }}</td><td class="r">{{ rtrim(rtrim((string) $i->quantity, '0'), '.') }}</td>
        <td class="r">{{ money($i->unit_price) }}</td><td class="r">{{ money($i->line_total) }}</td></tr>@endforeach
</tbody></table>
<table class="totals" style="width:45%;margin-left:auto;margin-top:8px">
    <tr><td>Subtotal</td><td class="r">{{ money($invoice->subtotal) }}</td></tr>
    @if($invoice->discount_amount > 0)<tr><td>Discount</td><td class="r">-{{ money($invoice->discount_amount) }}</td></tr>@endif
    <tr><td>{{ settings('general.tax_name', 'Tax') }} ({{ (float) $invoice->tax_rate }}%)</td><td class="r">{{ money($invoice->tax_amount) }}</td></tr>
    <tr class="grand"><td>Total</td><td class="r">{{ money($invoice->total) }}</td></tr>
    <tr><td>Paid</td><td class="r">{{ money($invoice->amount_paid) }}</td></tr>
    <tr><td><strong>Balance due</strong></td><td class="r"><strong>{{ money($invoice->balance) }}</strong></td></tr>
    @if($invoice->financing && in_array($invoice->financing->status, ['pending', 'approved']))
        <tr><td class="muted">Financed by {{ $invoice->financing->lender_name }}</td><td class="r muted">{{ money($invoice->financing->financed_amount) }}</td></tr>
        <tr><td class="muted">Due from customer</td><td class="r muted">{{ money($invoice->customerBalance()) }}</td></tr>
    @endif
</table>
@if($invoice->allocations->isNotEmpty())
<br><table class="items"><thead><tr><th>Payment</th><th>Date</th><th>Method</th><th class="r">Amount</th></tr></thead><tbody>
    @foreach($invoice->allocations as $a)@continue($a->payment->status !== 'completed')<tr><td>{{ $a->payment->payment_number }} ({{ $e['payment_types'][$a->payment->type] ?? '' }})</td><td>{{ fmt_date($a->payment->payment_date) }}</td>
        <td>{{ $e['payment_methods'][$a->payment->method] ?? '' }}</td><td class="r">{{ $a->payment->isRefund() ? '-' : '' }}{{ money($a->amount) }}</td></tr>@endforeach</tbody></table>
@endif
@if($invoice->notes)<p><strong>Notes:</strong> {{ $invoice->notes }}</p>@endif
@if($invoice->terms)<p class="muted" style="font-size:10px"><strong>Terms & conditions:</strong> {!! nl2br(e($invoice->terms)) !!}</p>@endif
</div>
