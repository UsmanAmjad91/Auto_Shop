@extends('layouts.app')
@section('title', 'Users')
@section('content')
<x-page-header title="Users & Account Approval" :breadcrumbs="['Administration' => null, 'Users' => null]">
    @can('users.create')<a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Add user</a>@endcan
</x-page-header>
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach(config('autoshop.enums.user_statuses') as $k => $l)
        <a href="{{ route('users.index', ['status' => $k]) }}" class="btn btn-sm {{ request('status') === $k ? 'btn-'.status_color($k) : 'btn-outline-'.status_color($k) }}">{{ $l }} <span class="badge text-bg-light">{{ $counts[$k] ?? 0 }}</span></a>
    @endforeach
    <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">All</a>
</div>
<x-filters for="users-table">
    <x-form.select name="status" label="Status" :options="config('autoshop.enums.user_statuses')" placeholder="Any" col="col-6 col-md-3" />
    <x-form.select name="role" label="Role" :options="$roles->combine($roles)" placeholder="Any" col="col-6 col-md-3" />
</x-filters>
<x-datatable id="users-table" :url="route('users.index')" module="users" sort="created_at" :export-url="route('users.export')" :bulk-url="route('users.bulk')"
    :bulk="auth()->user()->can('users.approve') ? [['action' => 'approved', 'label' => 'Approve selected', 'confirm' => 'Approve :count account(s)?'], ['action' => 'rejected', 'label' => 'Reject selected', 'confirm' => 'Reject :count account(s)?', 'danger' => true], ['action' => 'suspended', 'label' => 'Suspend selected', 'confirm' => 'Suspend :count account(s)?', 'danger' => true]] : []"
    :columns="[
        ['key' => 'avatar', 'label' => '', 'type' => 'image'],
        ['key' => 'name', 'label' => 'Name', 'type' => 'link', 'sortable' => true],
        ['key' => 'email', 'label' => 'Email', 'sortable' => true],
        ['key' => 'phone', 'label' => 'Phone', 'hidden' => true],
        ['key' => 'roles', 'label' => 'Roles'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'sortable' => true],
        ['key' => 'email_verified', 'label' => 'Email ✓', 'type' => 'bool'],
        ['key' => 'two_factor', 'label' => '2FA', 'type' => 'bool'],
        ['key' => 'last_login_at', 'label' => 'Last login', 'sortable' => true],
        ['key' => 'created_at', 'label' => 'Registered', 'sortable' => true],
        ['key' => 'actions', 'label' => '', 'type' => 'actions'],
    ]" />
@endsection
