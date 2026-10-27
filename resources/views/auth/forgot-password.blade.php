@extends('layouts.guest')
@section('title', 'Forgot password')
@section('content')
    <h2 class="h5 mb-1">Reset your password</h2>
    <p class="small text-secondary">Enter your email and we'll send you a secure reset link (valid for 60 minutes).</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3"><x-form.input name="email" type="email" label="Email" required autofocus /></div>
        <button class="btn btn-primary w-100">Email reset link</button>
    </form>
@endsection
@section('below')<a href="{{ route('login') }}">Back to sign in</a>@endsection
