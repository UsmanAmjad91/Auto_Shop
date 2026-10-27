<table style="width:100%;margin-bottom:12px"><tr>
    <td>@if($logo)<img src="{{ $logo }}" style="max-height:50px">@endif<div style="font-weight:bold;font-size:16px">{{ settings('general.company_name') }}</div>
        <div>{{ settings('general.company_address') }}</div><div>{{ settings('general.company_phone') }} {{ settings('general.company_email') }}</div></td>
    <td style="text-align:right"><div style="font-size:18px;font-weight:bold">STATEMENT</div><div>{{ fmt_date($from) }} — {{ fmt_date($to) }}</div></td>
</tr></table>
<p><strong>{{ $customer->display_name }}</strong><br>{{ $customer->fullAddress() }}<br>{{ $customer->email }} {{ $customer->phone }}</p>
<table class="table table-sm">
    <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th class="text-end r">Charges</th><th class="text-end r">Payments</th><th class="text-end r">Balance</th></tr></thead>
    <tbody>
        <tr><td>{{ fmt_date($from) }}</td><td></td><td><em>Opening balance</em></td><td></td><td></td><td class="text-end r">{{ money($opening) }}</td></tr>
        @foreach($lines as $l)<tr><td>{{ fmt_date($l['date']) }}</td><td>{{ $l['ref'] }}</td><td>{{ $l['desc'] }}</td>
            <td class="text-end r">{{ $l['debit'] ? money($l['debit']) : '' }}</td><td class="text-end r">{{ $l['credit'] ? money($l['credit']) : '' }}</td><td class="text-end r">{{ money($l['balance']) }}</td></tr>@endforeach
        <tr style="font-weight:bold"><td colspan="5">Closing balance</td><td class="text-end r">{{ money($closing) }}</td></tr>
    </tbody>
</table>
<p style="font-size:10px;color:#666">Balances include amounts awaiting settlement from financing lenders, if any.</p>
