@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
    <h2 class="h5 mb-3">Sign in</h2>
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <div class="mb-3"><x-form.input name="email" type="email" label="Email" required autofocus autocomplete="username" /></div>
        <div class="mb-3"><x-form.input name="password" type="password" label="Password" required autocomplete="current-password" /></div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="remember" id="remember" value="1"><label class="form-check-label small" for="remember">Remember me</label></div>
            <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
        </div>
        <button class="btn btn-primary w-100">Sign in</button>
    </form>
@endsection
@section('below')
    New here? <a href="{{ route('register') }}">Create an account</a>
@endsection
