@extends('layouts.guest')
@section('title', 'Choose a new password')
@section('content')
    <h2 class="h5 mb-3">Choose a new password</h2>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mb-3"><x-form.input name="email" type="email" label="Email" :value="$email" required /></div>
        <div class="mb-3"><x-form.input name="password" type="password" label="New password" required autocomplete="new-password" /></div>
        <div class="mb-3"><x-form.input name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" /></div>
        <button class="btn btn-primary w-100">Reset password</button>
    </form>
@endsection
