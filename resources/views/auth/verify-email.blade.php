@extends('layouts.guest')
@section('title', 'Verify email')
@section('content')
    <h2 class="h5">Verify your email address</h2>
    <p class="small text-secondary">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Click it to continue. Didn't receive it?</p>
    <form method="POST" action="{{ route('verification.send') }}" class="mb-2">@csrf<button class="btn btn-primary w-100">Resend verification email</button></form>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-link w-100">Sign out</button></form>
@endsection
