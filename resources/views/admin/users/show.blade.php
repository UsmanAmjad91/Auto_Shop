@extends('layouts.app')
@section('title', $user->name)
@section('content')
<x-page-header :title="$user->name" :breadcrumbs="['Users' => route('users.index'), $user->name => null]">
    @can('update', $user)<a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>@endcan
    @can('delete', $user)<a href="{{ route('users.destroy', $user) }}" data-method="DELETE" data-confirm="Delete this user?" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a>@endcan
</x-page-header>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-body text-center">
            <img src="{{ $user->avatarUrl() }}" class="rounded-circle mb-2" width="80" height="80" style="object-fit:cover" alt="">
            <h5 class="mb-0">{{ $user->name }}</h5><div class="text-secondary small">{{ $user->email }}</div>
            <div class="mt-2"><x-status :status="$user->status" /> @foreach($user->roles as $r)<span class="badge text-bg-light border">{{ $r->name }}</span>@endforeach</div>
        </div>
        <dl class="dl-grid card-body border-top">
            <dt>Phone</dt><dd>{{ $user->phone ?: '—' }}</dd>
            <dt>Email verified</dt><dd>{{ $user->email_verified_at ? fmt_date($user->email_verified_at, true) : 'No' }}</dd>
            <dt>Phone verified</dt><dd>{{ $user->phone_verified_at ? 'Yes' : 'No' }}</dd>
            <dt>2FA</dt><dd>{{ $user->hasTwoFactorEnabled() ? strtoupper($user->two_factor_method) : 'Off' }}</dd>
            <dt>Registered</dt><dd>{{ fmt_date($user->created_at, true) }}</dd>
            <dt>Approved</dt><dd>{{ $user->approved_at ? fmt_date($user->approved_at, true) : '—' }}</dd>
            <dt>Last login</dt><dd>{{ $user->last_login_at ? $user->last_login_at->diffForHumans().' ('.$user->last_login_ip.')' : 'Never' }}</dd>
            <dt>Employee</dt><dd>@if($user->employee)<a href="{{ route('employees.show', $user->employee) }}">{{ $user->employee->full_name }}</a>@else — @endif</dd>
        </dl></div>

        @can('approve', $user)
        <div class="card mt-3"><div class="card-header">Account approval</div><div class="card-body">
            <form method="POST" action="{{ route('users.status', $user) }}">@csrf
                <x-form.select name="status" label="Set status" :options="config('autoshop.enums.user_statuses')" :value="$user->status" />
                <div class="mt-2"><label class="form-label">Roles to grant on approval</label>
                    <select name="roles[]" multiple data-searchable class="form-select">
                        @foreach(\Spatie\Permission\Models\Role::orderBy('name')->pluck('name') as $r)
                            @continue($r === 'Super Administrator' && ! auth()->user()->hasRole('Super Administrator'))
                            <option value="{{ $r }}" @selected($user->hasRole($r))>{{ $r }}</option>
                        @endforeach
                    </select></div>
                <x-form.textarea name="notes" label="Notes (sent to the user for rejection/suspension)" rows="2" />
                <button class="btn btn-primary mt-2">Update account</button>
            </form>
        </div></div>
        @endcan
    </div>
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header">Approval history</div><div class="table-responsive"><table class="table table-sm mb-0 small">
            <thead><tr><th>Requested</th><th>Status</th><th>Reviewed by</th><th>When</th><th>Notes</th></tr></thead>
            <tbody>@forelse($user->approvalRequests as $a)<tr><td>{{ fmt_date($a->created_at, true) }}</td><td><x-status :status="$a->status" /></td><td>{{ $a->reviewer?->name }}</td><td>{{ $a->reviewed_at ? fmt_date($a->reviewed_at, true) : '' }}</td><td>{{ $a->notes }}</td></tr>
            @empty<tr><td colspan="5" class="text-secondary">No approval requests.</td></tr>@endforelse</tbody></table></div></div>
        <div class="card mb-3"><div class="card-header">Login history</div><div class="table-responsive"><table class="table table-sm mb-0 small">
            <thead><tr><th>Date</th><th>IP</th><th>Device</th><th>Result</th></tr></thead>
            <tbody>@foreach($logins as $l)<tr><td>{{ $l->created_at->format('Y-m-d H:i') }}</td><td>{{ $l->ip_address }}</td><td>{{ \Illuminate\Support\Str::limit($l->user_agent, 50) }}</td>
                <td>{!! $l->successful ? '<span class="text-success">Success</span>' : '<span class="text-danger">'.e($l->reason).'</span>' !!}</td></tr>@endforeach</tbody></table></div></div>
        <div class="card"><div class="card-header">Recent activity</div><ul class="list-group list-group-flush small">
            @forelse($activity as $a)<li class="list-group-item d-flex justify-content-between"><span>{{ $a->description ?? $a->event }}</span><span class="text-secondary">{{ $a->created_at->diffForHumans() }}</span></li>
            @empty<li class="list-group-item text-secondary">No activity.</li>@endforelse</ul></div>
    </div>
</div>
@endsection
