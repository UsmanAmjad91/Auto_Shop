@extends('layouts.guest')
@section('title', 'Two-factor authentication')
@section('content')
    <h2 class="h5">Two-factor authentication</h2>
    <p class="small text-secondary">
        @if($method === 'totp') Enter the 6-digit code from your authenticator app.
        @else We sent a 6-digit code by {{ $method === 'sms' ? 'SMS' : 'email' }}. @endif
    </p>
    <form method="POST" action="{{ route('two-factor.verify') }}">
        @csrf
        <div class="mb-3"><x-form.input name="code" label="Authentication code" inputmode="numeric" maxlength="10" autofocus autocomplete="one-time-code" /></div>
        <button class="btn btn-primary w-100">Verify</button>
    </form>
    @if($method !== 'totp')
        <form method="POST" action="{{ route('two-factor.send') }}" class="mt-2">@csrf<button class="btn btn-link btn-sm w-100">Send a new code</button></form>
    @endif
    <details class="mt-3"><summary class="small">Use a recovery code</summary>
        <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-2">
            @csrf
            <div class="input-group"><input name="recovery_code" class="form-control" placeholder="xxxxx-xxxxx"><button class="btn btn-outline-secondary">Use</button></div>
            @error('recovery_code')<div class="small text-danger">{{ $message }}</div>@enderror
        </form>
    </details>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="btn btn-link btn-sm w-100">Sign out</button></form>
@endsection
