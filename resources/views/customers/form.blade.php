@extends('layouts.app')
@section('title', $customer->exists ? 'Edit customer' : 'Add customer')
@section('content')
<x-page-header :title="$customer->exists ? 'Edit '.$customer->display_name : 'Add customer'" :breadcrumbs="['Customers' => route('customers.index'), ($customer->exists ? $customer->code : 'Add') => null]" />
<form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" class="card">
    @csrf @if($customer->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.select name="type" label="Customer type" :options="['individual' => 'Individual', 'business' => 'Business']" :value="$customer->type" col="col-md-3" />
        <x-form.input name="first_name" label="First name" :value="$customer->first_name" required col="col-md-3" />
        <x-form.input name="last_name" label="Last name" :value="$customer->last_name" col="col-md-3" />
        <x-form.input name="company_name" label="Company" :value="$customer->company_name" col="col-md-3" help="Required for business customers" />
        <x-form.input name="phone" label="Phone" :value="$customer->phone" col="col-md-4" />
        <x-form.input name="alt_phone" label="Alternate phone" :value="$customer->alt_phone" col="col-md-4" />
        <x-form.input name="email" type="email" label="Email" :value="$customer->email" col="col-md-4" />
        <x-form.input name="address" label="Street address" :value="$customer->address" col="col-md-6" />
        <x-form.input name="city" label="City" :value="$customer->city" col="col-md-2" />
        <x-form.input name="state" label="State" :value="$customer->state" col="col-md-2" />
        <x-form.input name="postal_code" label="Postal code" :value="$customer->postal_code" col="col-md-2" />
        <x-form.input name="country" label="Country" :value="$customer->country" col="col-md-4" />
        <x-form.input name="tax_number" label="Tax / VAT number" :value="$customer->tax_number" col="col-md-4" />
        <x-form.input name="driver_license" label="Driver license #" :value="$customer->driver_license" col="col-md-4" />
        <x-form.textarea name="notes" label="Notes" :value="$customer->notes" col="col-12" />
        <x-form.switch name="is_active" label="Active" :checked="$customer->is_active" col="col-12" />
    </div>
    <div class="card-footer text-end"><a href="{{ url()->previous() }}" class="btn btn-light">Cancel</a> <button class="btn btn-primary">Save customer</button></div>
</form>
@endsection
