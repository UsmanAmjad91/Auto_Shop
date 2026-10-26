@extends('layouts.guest')
@section('title', 'Create account')
@section('content')
    <h2 class="h5 mb-1">Create an account</h2>
    @if(settings()->bool('verification.admin_approval', true))
        <p class="small text-secondary">New accounts are reviewed by an administrator before access is granted.</p>
    @endif
    <form method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf
        <div class="mb-3"><x-form.input name="name" label="Full name" required autofocus autocomplete="name" /></div>
        <div class="mb-3"><x-form.input name="email" type="email" label="Email" required autocomplete="email" /></div>
        <div class="mb-3"><x-form.input name="phone" type="tel" label="Mobile phone" :required="settings()->bool('verification.sms')" placeholder="+1 555 123 4567" autocomplete="tel" /></div>
        <div class="mb-3"><x-form.input name="password" type="password" label="Password" required autocomplete="new-password"
            help="At least {{ settings('security.password_min_length', 8) }} characters with upper & lower case letters and a number." /></div>
        <div class="mb-3"><x-form.input name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" /></div>
        <div class="form-check mb-3">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" value="1" required>
            <label class="form-check-label small" for="terms">I agree to the acceptable use policy and the processing of my account data.</label>
        </div>
        <button class="btn btn-primary w-100">Register</button>
    </form>
@endsection
@section('below')
    Already registered? <a href="{{ route('login') }}">Sign in</a>
@endsection
