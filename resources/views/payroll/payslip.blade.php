<!DOCTYPE html><html><head><meta charset="utf-8"><title>Payslip</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111}table{width:100%;border-collapse:collapse}td,th{padding:5px 6px}
.box td{border-bottom:1px solid #eee}.r{text-align:right}h1{font-size:18px;margin:0}.muted{color:#666}.tot{font-weight:bold;background:#f3f4f6}</style></head><body>
<table><tr><td>@if($logo)<img src="{{ $logo }}" style="max-height:45px"><br>@endif<strong>{{ settings('general.company_name') }}</strong><br><span class="muted">{{ settings('general.company_address') }}</span></td>
    <td class="r"><h1>PAYSLIP</h1>{{ $entry->period->name }}<br>{{ fmt_date($entry->period->start_date) }} – {{ fmt_date($entry->period->end_date) }}<br>Pay date: {{ fmt_date($entry->period->pay_date) ?: '—' }}</td></tr></table>
<hr>
<table><tr><td><strong>{{ $entry->employee->full_name }}</strong><br>ID {{ $entry->employee->employee_code }} · {{ $entry->employee->job_title }}<br>{{ $entry->employee->department?->name }}</td>
    <td class="r">Pay type: {{ config('autoshop.enums.pay_types.'.$entry->pay_type) }}<br>Rate: {{ money($entry->rate) }} · OT ×{{ $entry->overtime_multiplier }}</td></tr></table>
<br>
<table class="box">
    <tr><th style="text-align:left">Earnings</th><th class="r">Units</th><th class="r">Amount</th></tr>
    <tr><td>Base pay</td><td class="r">{{ $entry->regular_hours }} h / {{ $entry->days_worked }} d</td><td class="r">{{ money($entry->base_pay) }}</td></tr>
    <tr><td>Overtime</td><td class="r">{{ $entry->overtime_hours }} h</td><td class="r">{{ money($entry->overtime_pay) }}</td></tr>
    <tr><td>Bonuses</td><td></td><td class="r">{{ money($entry->bonuses) }}</td></tr>
    <tr class="tot"><td>Gross earnings</td><td></td><td class="r">{{ money($entry->gross_earnings) }}</td></tr>
    <tr><td>Deductions</td><td></td><td class="r">-{{ money($entry->deductions) }}</td></tr>
    <tr><td>Advances recovered</td><td></td><td class="r">-{{ money($entry->advances) }}</td></tr>
    <tr class="tot"><td>Net pay</td><td></td><td class="r">{{ money($entry->net_pay) }}</td></tr>
    <tr><td>Paid to date</td><td></td><td class="r">{{ money($entry->amount_paid) }}</td></tr>
    <tr><td>Remaining payable</td><td></td><td class="r">{{ money($entry->remaining_payable) }}</td></tr>
</table>
@if($entry->payments->isNotEmpty())<br><table class="box"><tr><th style="text-align:left">Payments</th><th>Method</th><th>Reference</th><th class="r">Amount</th></tr>
    @foreach($entry->payments as $p)<tr><td>{{ fmt_date($p->payment_date) }}</td><td>{{ status_label($p->method) }}</td><td>{{ $p->reference }}</td><td class="r">{{ money($p->amount) }}</td></tr>@endforeach</table>@endif
@if($entry->notes)<p>{{ $entry->notes }}</p>@endif
<p class="muted" style="margin-top:20px">This payslip was generated from recorded attendance and configured pay rules. Statutory deductions are included only where entered.</p>
</body></html>
