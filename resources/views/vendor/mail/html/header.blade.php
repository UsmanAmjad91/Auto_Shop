@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (settings('general.logo'))
<img src="{{ asset('storage/'.settings('general.logo')) }}" class="logo" alt="{{ settings('general.company_name') }}" style="height:auto;max-height:60px;width:auto;">
@else
{{ settings('general.company_name', $slot) }}
@endif
</a>
</td>
</tr>
