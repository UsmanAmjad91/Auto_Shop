@extends('admin.settings._layout')
@section('settings')
@php $colors = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark', 'teal', 'purple']; @endphp
<div class="card mb-3"><div class="card-header">Vehicle repair statuses</div>
    <div class="card-body small text-secondary pb-0">System statuses drive automatic updates from service jobs (e.g. job "In Progress" → vehicle "Under Repair"). Rename or recolour them freely; deactivate instead of deleting.</div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Order</th><th>Name</th><th>Colour</th><th>Active</th><th></th></tr></thead>
        <tbody>@foreach($statuses as $s)
            @php $fid = 'st-'.$s->id; @endphp
            <tr>
                <td style="width:90px"><input form="{{ $fid }}" type="number" name="sort_order" value="{{ $s->sort_order }}" class="form-control form-control-sm"></td>
                <td><input form="{{ $fid }}" name="name" value="{{ $s->name }}" class="form-control form-control-sm" required><div class="small text-secondary">{{ $s->slug }} @if($s->is_system)· system @endif</div></td>
                <td><select form="{{ $fid }}" name="color" class="form-select form-select-sm">@foreach($colors as $c)<option @selected($s->color === $c)>{{ $c }}</option>@endforeach</select></td>
                <td><input form="{{ $fid }}" type="hidden" name="is_active" value="0"><input form="{{ $fid }}" type="checkbox" class="form-check-input" name="is_active" value="1" @checked($s->is_active)></td>
                <td class="text-end text-nowrap"><x-status :label="$s->name" :color="$s->color" />
                <form id="{{ $fid }}" method="POST" action="{{ route('settings.statuses.update', $s) }}" class="d-inline">@csrf @method('PUT')<button class="btn btn-sm btn-outline-primary">Save</button></form>
                @unless($s->is_system)<form method="POST" action="{{ route('settings.statuses.destroy', $s) }}" class="d-inline" onsubmit="return confirm('Delete status?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>@endunless
                </td></tr>
        @endforeach</tbody></table></div></div>
<form method="POST" action="{{ route('settings.statuses.store') }}" class="card"><div class="card-body row g-2 align-items-end">@csrf
    <x-form.input name="name" label="New status name" required col="col-md-6" />
    <x-form.select name="color" label="Colour" :options="array_combine($colors, $colors)" col="col-md-3" />
    <div class="col-md-3"><button class="btn btn-primary w-100">Add status</button></div>
</div></form>
@endsection
