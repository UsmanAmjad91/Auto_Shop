@extends('layouts.app')
@section('title', 'Roles & Permissions')
@section('content')
<x-page-header title="Roles & Permissions" :breadcrumbs="['Administration' => null, 'Roles' => null]">
    @can('roles.create')<a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New role</a>@endcan
</x-page-header>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Role</th><th>Users</th><th>Permissions</th><th></th></tr></thead>
    <tbody>@foreach($roles as $r)<tr>
        <td class="fw-semibold">{{ $r->name }} @if(in_array($r->name, $protected))<span class="badge text-bg-dark">Protected</span>@endif</td>
        <td>{{ $r->users_count }}</td><td>{{ in_array($r->name, $protected) ? 'All' : $r->permissions_count }}</td>
        <td class="text-end">
            @can('roles.edit')<a href="{{ route('roles.edit', $r) }}" class="btn btn-sm btn-outline-secondary">{{ in_array($r->name, $protected) ? 'View' : 'Edit permissions' }}</a>@endcan
            @if(! in_array($r->name, $protected))@can('roles.delete')<a href="{{ route('roles.destroy', $r) }}" data-method="DELETE" data-confirm="Delete role {{ $r->name }}?" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a>@endcan @endif
        </td></tr>@endforeach</tbody>
</table></div></div>
@endsection
