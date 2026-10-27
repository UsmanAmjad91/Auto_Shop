@extends('layouts.guest')
@section('title', 'Awaiting approval')
@section('content')
    <div class="text-center">
        <i class="bi bi-hourglass-split display-5 text-warning"></i>
        <h2 class="h5 mt-2">Your account is awaiting approval</h2>
        <p class="small text-secondary">Thanks for registering, {{ $user->name }}. An administrator has been notified and will review your account.
            You'll receive an email once it's approved.</p>
        @if(settings()->bool('verification.email') && ! $user->hasVerifiedEmail())
            <div class="alert alert-info small">Please also verify your email address using the link we sent you.</div>
            <form method="POST" action="{{ route('verification.send') }}" class="mb-2">@csrf<button class="btn btn-outline-primary btn-sm">Resend verification email</button></form>
        @endif
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary">Sign out</button></form>
    </div>
@endsection
