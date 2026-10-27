@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'Add user')
@section('content')
<x-page-header :title="$user->exists ? 'Edit '.$user->name : 'Add user'" :breadcrumbs="['Users' => route('users.index'), ($user->exists ? 'Edit' : 'Add') => null]" />
<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card"><div class="card-body">
    @csrf @if($user->exists) @method('PUT') @endif
    <div class="row g-3">
        <x-form.input name="name" label="Full name" :value="$user->name" required col="col-md-6" />
        <x-form.input name="email" type="email" label="Email" :value="$user->email" required col="col-md-6" />
        <x-form.input name="phone" label="Phone" :value="$user->phone" col="col-md-4" />
        <x-form.select name="status" label="Account status" :options="config('autoshop.enums.user_statuses')" :value="$user->status" required col="col-md-4"
            :disabled="$user->exists && $user->id === auth()->id()" />
        @if($user->exists && $user->id === auth()->id())<input type="hidden" name="status" value="{{ $user->status }}">@endif
        <div class="col-md-4">
            <label class="form-label">Roles</label>
            <select name="roles[]" multiple data-searchable class="form-select" @disabled($user->exists && $user->id === auth()->id())>
                @foreach($roles as $r)<option value="{{ $r }}" @selected(in_array($r, old('roles', $user->exists ? $user->getRoleNames()->all() : [])))>{{ $r }}</option>@endforeach
            </select>
            @if($user->exists && $user->id === auth()->id())<div class="form-text">You cannot change your own roles.</div>@endif
        </div>
        <x-form.input name="password" type="password" label="{{ $user->exists ? 'New password (leave blank to keep)' : 'Password' }}" :required="! $user->exists" col="col-md-6" autocomplete="new-password" />
        <x-form.input name="password_confirmation" type="password" label="Confirm password" col="col-md-6" autocomplete="new-password" />
        @unless($user->exists)<x-form.switch name="mark_verified" label="Mark email as verified" :checked="true" col="col-12" />@endunless
    </div>
</div><div class="card-footer text-end"><a href="{{ url()->previous() }}" class="btn btn-light">Cancel</a> <button class="btn btn-primary">Save</button></div></form>
@endsection
