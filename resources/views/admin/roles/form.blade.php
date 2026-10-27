@extends('layouts.app')
@section('title', $role->exists ? 'Edit role' : 'New role')
@section('content')
@php $locked = $locked ?? false; @endphp
<x-page-header :title="$role->exists ? 'Role: '.$role->name : 'New role'" :breadcrumbs="['Roles' => route('roles.index'), ($role->exists ? $role->name : 'New') => null]" />
@if($locked)<div class="alert alert-info">This role is protected and always has every permission.</div>@endif
<form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}">
    @csrf @if($role->exists) @method('PUT') @endif
    <div class="card mb-3"><div class="card-body"><x-form.input name="name" label="Role name" :value="$role->name" required :disabled="$locked" /></div></div>
    <div class="card"><div class="card-header d-flex justify-content-between align-items-center">Permissions
        @unless($locked)<span><button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.perm').forEach(c=>c.checked=true)">Select all</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.perm').forEach(c=>c.checked=false)">Clear</button></span>@endunless</div>
        <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Module</th><th>Permissions</th><th class="text-end">Row</th></tr></thead>
            <tbody>
            @foreach($matrix as $module => $actions)
                <tr><td class="fw-semibold text-nowrap">{{ status_label($module) }}</td>
                    <td><div class="d-flex flex-wrap gap-3">
                        @foreach($actions as $action => $perm)
                            <div class="form-check"><input class="form-check-input perm perm-{{ $module }}" type="checkbox" name="permissions[]" value="{{ $perm }}" id="p-{{ $perm }}"
                                @checked($locked || in_array($perm, old('permissions', $granted))) @disabled($locked)>
                                <label class="form-check-label small" for="p-{{ $perm }}">{{ status_label($action) }}</label></div>
                        @endforeach
                    </div></td>
                    <td class="text-end">@unless($locked)<button type="button" class="btn btn-link btn-sm p-0" onclick="var b=document.querySelectorAll('.perm-{{ $module }}');var on=[...b].some(c=>!c.checked);b.forEach(c=>c.checked=on)">toggle</button>@endunless</td></tr>
            @endforeach
            </tbody></table></div>
        @unless($locked)<div class="card-footer text-end"><a href="{{ route('roles.index') }}" class="btn btn-light">Cancel</a> <button class="btn btn-primary">Save role</button></div>@endunless
    </div>
</form>
<p class="small text-secondary mt-2"><strong>view_all</strong> grants record-level access to every record (otherwise users see only records assigned to them). <strong>view_costs / financials.view</strong> expose purchase prices, costs and profit.</p>
@endsection
