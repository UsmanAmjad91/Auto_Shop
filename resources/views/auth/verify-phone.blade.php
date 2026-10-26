@extends('layouts.guest')
@section('title', 'Verify phone')
@section('content')
    <h2 class="h5">Verify your mobile number</h2>
    <p class="small text-secondary">We'll text you a 6-digit code. Standard carrier rates may apply.</p>
    <form method="POST" action="{{ route('phone.send') }}" class="mb-3">
        @csrf
        <div class="input-group">
            <input type="tel" name="phone" class="form-control" value="{{ old('phone', auth()->user()->phone) }}" placeholder="+1 555 123 4567" required>
            <button class="btn btn-outline-primary">Send code</button>
        </div>
    </form>
    <form method="POST" action="{{ route('phone.verify.store') }}">
        @csrf
        <div class="mb-3"><x-form.input name="code" label="Verification code" inputmode="numeric" maxlength="6" required autocomplete="one-time-code" /></div>
        <button class="btn btn-primary w-100">Verify</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf<button class="btn btn-link w-100">Sign out</button></form>
@endsection
