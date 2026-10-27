@extends('admin.settings._layout')
@section('settings')
<div class="card mb-3"><div class="card-header">Custom vehicle fields</div>
    <div class="card-body small text-secondary pb-0">These fields appear on the vehicle form and details page and are validated automatically.</div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Label</th><th>Key</th><th>Type</th><th>Options</th><th>Required</th><th>Active</th><th>Order</th><th></th></tr></thead>
        <tbody>@forelse($fields as $f)
            @php $fid = 'cf-'.$f->id; @endphp
            <tr>
                <td><input form="{{ $fid }}" name="label" value="{{ $f->label }}" class="form-control form-control-sm" required></td>
                <td class="small font-monospace">{{ $f->key }}</td>
                <td><select form="{{ $fid }}" name="type" class="form-select form-select-sm">@foreach(\App\Models\CustomField::TYPES as $k => $l)<option value="{{ $k }}" @selected($f->type === $k)>{{ $l }}</option>@endforeach</select></td>
                <td><input form="{{ $fid }}" name="options" value="{{ implode(', ', $f->options ?? []) }}" class="form-control form-control-sm" placeholder="a, b, c"></td>
                <td><input form="{{ $fid }}" type="hidden" name="is_required" value="0"><input form="{{ $fid }}" type="checkbox" class="form-check-input" name="is_required" value="1" @checked($f->is_required)></td>
                <td><input form="{{ $fid }}" type="hidden" name="is_active" value="0"><input form="{{ $fid }}" type="checkbox" class="form-check-input" name="is_active" value="1" @checked($f->is_active)></td>
                <td style="width:80px"><input form="{{ $fid }}" type="number" name="sort_order" value="{{ $f->sort_order }}" class="form-control form-control-sm"></td>
                <td class="text-nowrap"><form id="{{ $fid }}" method="POST" action="{{ route('settings.custom-fields.update', $f) }}" class="d-inline">@csrf @method('PUT')<button class="btn btn-sm btn-outline-primary">Save</button></form>
                <form method="POST" action="{{ route('settings.custom-fields.destroy', $f) }}" class="d-inline" onsubmit="return confirm('Delete field?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
        @empty<tr><td colspan="8" class="text-secondary small">No custom fields yet.</td></tr>@endforelse</tbody></table></div></div>
<form method="POST" action="{{ route('settings.custom-fields.store') }}" class="card"><div class="card-body row g-2 align-items-end">@csrf
    <x-form.input name="label" label="Label" required col="col-md-4" />
    <x-form.select name="type" label="Type" :options="\App\Models\CustomField::TYPES" col="col-md-3" />
    <x-form.input name="options" label="Dropdown options (comma separated)" col="col-md-5" />
    <input type="hidden" name="is_active" value="1">
    <x-form.switch name="is_required" label="Required" col="col-md-3" />
    <div class="col-md-3 ms-auto"><button class="btn btn-primary w-100">Add field</button></div>
</div></form>
@endsection
