@extends('layouts.app')
@section('title', 'Security')
@section('content')
<x-page-header title="Security & Sessions" :breadcrumbs="['Profile' => route('profile.edit'), 'Security' => null]" />

@if(session('new_token'))
    <div class="alert alert-warning"><strong>New API token</strong> — copy it now, it will not be shown again:
        <div class="font-monospace small bg-body p-2 mt-2 border rounded user-select-all">{{ session('new_token') }}</div></div>
@endif
@if(session('recovery_codes'))
    <div class="alert alert-info"><strong>Recovery codes</strong> — store these somewhere safe. Each can be used once if you lose access to your second factor.
        <div class="row row-cols-2 row-cols-md-4 g-1 mt-2 font-monospace small">@foreach(session('recovery_codes') as $c)<div class="col">{{ $c }}</div>@endforeach</div></div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-header">Two-factor authentication</div><div class="card-body">
            @if($mode === 'off')
                <p class="text-secondary small mb-0">Two-factor authentication is disabled by the administrator.</p>
            @elseif($user->hasTwoFactorEnabled())
                <p><span class="badge text-bg-success">Enabled</span> using <strong>{{ ['totp' => 'authenticator app', 'email' => 'email codes', 'sms' => 'SMS codes'][$user->two_factor_method] }}</strong>
                    since {{ fmt_date($user->two_factor_confirmed_at) }}.</p>
                <form method="POST" action="{{ route('profile.2fa.codes') }}" class="row g-2 mb-2">@csrf
                    <div class="col-auto"><input type="password" name="password" class="form-control form-control-sm" placeholder="Current password" required></div>
                    <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">Regenerate recovery codes</button></div>
                </form>
                @if($mode !== 'required')
                <form method="POST" action="{{ route('profile.2fa.disable') }}" class="row g-2">@csrf @method('DELETE')
                    <div class="col-auto"><input type="password" name="password" class="form-control form-control-sm" placeholder="Current password" required></div>
                    <div class="col-auto"><button class="btn btn-sm btn-outline-danger">Disable 2FA</button></div>
                </form>
                @endif
            @elseif($qr)
                <p class="small">Scan this QR code with Google Authenticator, Microsoft Authenticator, 1Password, etc., then enter the 6-digit code.</p>
                <div class="d-flex gap-3 flex-wrap align-items-center">
                    <div class="bg-white p-2 rounded">{!! $qr !!}</div>
                    <div class="small">Or enter key manually:<div class="font-monospace user-select-all">{{ $secret }}</div></div>
                </div>
                <form method="POST" action="{{ route('profile.2fa.confirm') }}" class="row g-2 mt-2">@csrf
                    <div class="col-auto"><input name="code" class="form-control @error('code') is-invalid @enderror" placeholder="123456" inputmode="numeric" autocomplete="one-time-code" required></div>
                    <div class="col-auto"><button class="btn btn-primary">Confirm & enable</button></div>
                </form>
            @else
                <p class="small text-secondary">Add a second step at sign-in. {{ $mode === 'required' ? 'Your administrator requires this.' : '' }}</p>
                <form method="POST" action="{{ route('profile.2fa.totp') }}" class="mb-3">@csrf<button class="btn btn-primary btn-sm"><i class="bi bi-phone"></i> Use an authenticator app (recommended)</button></form>
                <form method="POST" action="{{ route('profile.2fa.otp') }}" class="row g-2">@csrf
                    <div class="col-sm-4"><select name="method" class="form-select form-select-sm"><option value="email">Email codes (free)</option><option value="sms">SMS codes</option></select></div>
                    <div class="col-sm-5"><input type="password" name="password" class="form-control form-control-sm" placeholder="Current password" required></div>
                    <div class="col-sm-3"><button class="btn btn-sm btn-outline-primary w-100">Enable</button></div>
                </form>
            @endif
        </div></div>

        <div class="card mt-3"><div class="card-header">API tokens</div><div class="card-body">
            <p class="small text-secondary">Personal access tokens for the REST API (<code>/api/v1</code>). Tokens carry your permissions.</p>
            <form method="POST" action="{{ route('profile.tokens.store') }}" class="input-group input-group-sm mb-3">@csrf
                <input name="name" class="form-control" placeholder="Token name (e.g. Mobile app)" required><button class="btn btn-outline-primary">Create token</button>
            </form>
            <ul class="list-group list-group-flush small">
                @forelse($tokens as $t)
                    <li class="list-group-item d-flex justify-content-between px-0"><span>{{ $t->name }} <span class="text-secondary">· last used {{ $t->last_used_at?->diffForHumans() ?? 'never' }} · expires {{ fmt_date($t->expires_at) }}</span></span>
                        <form method="POST" action="{{ route('profile.tokens.destroy', $t->id) }}">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0">Revoke</button></form></li>
                @empty <li class="list-group-item px-0 text-secondary">No tokens.</li> @endforelse
            </ul>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header d-flex justify-content-between align-items-center">Active sessions
            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#logout-others">Sign out other sessions</button></div>
            <div class="collapse" id="logout-others"><form method="POST" action="{{ route('profile.sessions.others') }}" class="p-3 border-bottom row g-2">@csrf @method('DELETE')
                <div class="col"><input type="password" name="password" class="form-control form-control-sm" placeholder="Confirm current password" required></div>
                <div class="col-auto"><button class="btn btn-sm btn-danger">Confirm</button></div></form></div>
            <ul class="list-group list-group-flush small">
                @foreach($sessions as $s)
                    <li class="list-group-item d-flex justify-content-between gap-2">
                        <div><i class="bi bi-{{ str_contains(strtolower($s->agent), 'mobile') ? 'phone' : 'laptop' }} me-1"></i>{{ \Illuminate\Support\Str::limit($s->agent, 70) }}
                            <div class="text-secondary">{{ $s->ip }} · {{ $s->last_activity->diffForHumans() }}</div></div>
                        @if($s->current)<span class="badge text-bg-success align-self-center">This device</span>
                        @else<form method="POST" action="{{ route('profile.sessions.destroy', $s->id) }}">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger">End</button></form>@endif
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card mt-3"><div class="card-header">Login history</div>
            <div class="table-responsive"><table class="table table-sm mb-0 small">
                <thead><tr><th>Date</th><th>IP</th><th>Result</th></tr></thead>
                <tbody>@foreach($logins as $l)<tr><td>{{ $l->created_at->format('Y-m-d H:i') }}</td><td>{{ $l->ip_address }}</td>
                    <td>@if($l->successful)<span class="text-success">Success</span>@else<span class="text-danger">Failed ({{ $l->reason }})</span>@endif</td></tr>@endforeach</tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
