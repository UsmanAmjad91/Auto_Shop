@extends('layouts.app')
@section('title', 'My Profile')
@section('content')
<x-page-header title="My Profile" :breadcrumbs="['Dashboard' => route('dashboard'), 'Profile' => null]">
    <a href="{{ route('profile.security') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-shield-lock"></i> Security & sessions</a>
</x-page-header>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-header">Profile information</div><div class="card-body">
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="{{ $user->avatarUrl() }}" class="rounded-circle" width="64" height="64" style="object-fit:cover" alt="">
                    <div class="flex-fill"><x-form.input name="avatar" type="file" label="Profile photo" accept="image/*" /></div>
                </div>
                <div class="row g-3">
                    <x-form.input name="name" label="Full name" :value="$user->name" required col="col-12" />
                    <x-form.input name="email" type="email" label="Email" :value="$user->email" required col="col-md-6"
                        :help="$user->hasVerifiedEmail() ? 'Verified' : 'Not verified'" />
                    <x-form.input name="phone" label="Mobile phone" :value="$user->phone" col="col-md-6" :help="$user->phone_verified_at ? 'Verified' : null" />
                </div>
                <div class="mt-3"><button class="btn btn-primary">Save profile</button></div>
            </form>
        </div></div>

        <div class="card mt-3"><div class="card-header">Change password</div><div class="card-body">
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf @method('PUT')
                <div class="row g-3">
                    <x-form.input name="current_password" type="password" label="Current password" required col="col-12" autocomplete="current-password" />
                    <x-form.input name="password" type="password" label="New password" required col="col-md-6" autocomplete="new-password" />
                    <x-form.input name="password_confirmation" type="password" label="Confirm new password" required col="col-md-6" autocomplete="new-password" />
                </div>
                <div class="mt-3"><button class="btn btn-primary">Update password</button></div>
            </form>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header">Preferences &amp; notifications</div><div class="card-body">
            <form method="POST" action="{{ route('profile.preferences') }}" data-ajax>
                @csrf @method('PUT')
                <input type="hidden" name="save_notifications" value="1">
                <x-form.select name="theme" label="Theme" :options="['auto' => 'Automatic (system)', 'light' => 'Light', 'dark' => 'Dark']" :value="$user->theme" />
                <table class="table table-sm mt-3 align-middle">
                    <thead><tr><th>Event</th><th class="text-center">In-app</th><th class="text-center">Email</th></tr></thead>
                    <tbody>
                    @foreach($events as $key => $label)
                        @php $pref = $user->notificationSettings->firstWhere('event', $key); @endphp
                        <tr><td class="small">{{ $label }}</td>
                            <td class="text-center"><input type="hidden" name="notifications[{{ $key }}][via_database]" value="0"><input class="form-check-input" type="checkbox" name="notifications[{{ $key }}][via_database]" value="1" @checked($pref ? $pref->via_database : true)></td>
                            <td class="text-center"><input type="hidden" name="notifications[{{ $key }}][via_mail]" value="0"><input class="form-check-input" type="checkbox" name="notifications[{{ $key }}][via_mail]" value="1" @checked($pref ? $pref->via_mail : true)></td></tr>
                    @endforeach
                    </tbody>
                </table>
                <button class="btn btn-primary">Save preferences</button>
            </form>
        </div></div>
        @if($user->employee)
            <div class="card mt-3"><div class="card-body">
                <div class="small text-secondary">Linked employee profile</div>
                <div class="fw-semibold">{{ $user->employee->full_name }} · {{ $user->employee->employee_code }}</div>
                <div class="small">{{ $user->employee->job_title }}</div>
            </div></div>
        @endif
    </div>
</div>
@endsection
