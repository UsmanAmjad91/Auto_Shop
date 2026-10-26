@extends('layouts.app')
@section('title', 'System Settings')
@section('content')
<x-page-header title="System Settings" :breadcrumbs="['Administration' => null, 'Settings' => null]" />
<div class="row g-3">
    @foreach($sections as $key => $s)
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <a href="{{ route('settings.section', $key) }}" class="card h-100 text-decoration-none text-body"><div class="card-body d-flex gap-3">
                <i class="bi bi-{{ $s['icon'] }} fs-3 text-primary"></i><div><div class="fw-semibold">{{ $s['title'] }}</div><div class="small text-secondary">{{ $s['desc'] }}</div></div>
            </div></a>
        </div>
    @endforeach
    <div class="col-sm-6 col-lg-4 col-xl-3"><a href="{{ route('users.index') }}" class="card h-100 text-decoration-none text-body"><div class="card-body d-flex gap-3">
        <i class="bi bi-person-gear fs-3 text-primary"></i><div><div class="fw-semibold">Users</div><div class="small text-secondary">Accounts, approval and suspension</div></div></div></a></div>
    <div class="col-sm-6 col-lg-4 col-xl-3"><a href="{{ route('roles.index') }}" class="card h-100 text-decoration-none text-body"><div class="card-body d-flex gap-3">
        <i class="bi bi-shield-lock fs-3 text-primary"></i><div><div class="fw-semibold">Roles & Permissions</div><div class="small text-secondary">Custom roles and permission matrix</div></div></div></a></div>
    <div class="col-sm-6 col-lg-4 col-xl-3"><a href="{{ route('audit-logs.index') }}" class="card h-100 text-decoration-none text-body"><div class="card-body d-flex gap-3">
        <i class="bi bi-journal-text fs-3 text-primary"></i><div><div class="fw-semibold">Audit Logs</div><div class="small text-secondary">Who changed what and when</div></div></div></a></div>
</div>
@endsection
