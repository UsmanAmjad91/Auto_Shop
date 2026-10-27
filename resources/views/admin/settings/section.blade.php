@extends('admin.settings._layout')
@section('settings')
<form method="POST" action="{{ route('settings.save', $section) }}" enctype="multipart/form-data" class="card" id="settings-{{ $section }}">
    @csrf
    <div class="card-body">
        @if($section === 'theme')<div class="alert alert-info small py-2">Changes preview live on this page. Click <strong>Save</strong> to apply for everyone. Users can still pick light/dark individually.</div>@endif
        @if($section === 'sms')<div class="alert alert-warning small py-2">SMS delivery is billed by the provider/carrier — there is no free SMS. For a low-cost option, use <strong>email codes</strong> for two-factor authentication (Verification settings).</div>@endif
        @if($section === 'verification')<div class="alert alert-info small py-2">Mandatory protections always stay on regardless of these toggles: password hashing, CSRF protection, login rate limiting, session expiry and role permissions.</div>@endif
        @include('admin.settings._fields')
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">Save settings</button></div>
</form>

@if($section === 'email')
    <form method="POST" action="{{ route('settings.email.test') }}" class="card mt-3"><div class="card-body">@csrf
        <label class="form-label">Send a test email</label>
        <div class="input-group"><input type="email" name="to" class="form-control" value="{{ auth()->user()->email }}" required><button class="btn btn-outline-primary">Send test</button></div>
        <div class="form-text">Current mailer: <code>{{ config('mail.default') }}</code>. Save settings first, then test.</div>
    </div></form>
@endif
@if($section === 'sms')
    <form method="POST" action="{{ route('settings.sms.test') }}" class="card mt-3"><div class="card-body">@csrf
        <label class="form-label">Send a test SMS</label>
        <div class="input-group"><input type="tel" name="to" class="form-control" placeholder="+15551234567" required><button class="btn btn-outline-primary">Send test</button></div>
    </div></form>
@endif
@endsection
