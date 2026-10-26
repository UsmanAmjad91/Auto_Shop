@extends('layouts.app')
@section('title', $supplier->exists ? 'Edit supplier' : 'Add supplier')
@section('content')
<x-page-header :title="$supplier->exists ? 'Edit '.$supplier->name : 'Add supplier'" :breadcrumbs="['Suppliers' => route('suppliers.index'), ($supplier->exists ? $supplier->name : 'Add') => null]" />
<form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="card">
    @csrf @if($supplier->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="name" label="Supplier name" :value="$supplier->name" required col="col-md-6" />
        <x-form.input name="contact_person" label="Contact person" :value="$supplier->contact_person" col="col-md-6" />
        <x-form.input name="phone" label="Phone" :value="$supplier->phone" col="col-md-4" />
        <x-form.input name="email" type="email" label="Email" :value="$supplier->email" col="col-md-4" />
        <x-form.input name="website" type="url" label="Website" :value="$supplier->website" col="col-md-4" placeholder="https://" />
        <x-form.input name="address" label="Address" :value="$supplier->address" col="col-md-6" />
        <x-form.input name="city" label="City" :value="$supplier->city" col="col-md-3" />
        <x-form.input name="country" label="Country" :value="$supplier->country" col="col-md-3" />
        <x-form.input name="tax_number" label="Tax number" :value="$supplier->tax_number" col="col-md-4" />
        <x-form.input name="payment_terms_days" type="number" min="0" label="Payment terms (days)" :value="$supplier->payment_terms_days" col="col-md-4" />
        <div class="col-md-4 d-flex align-items-end"><x-form.switch name="is_active" label="Active" :checked="$supplier->is_active" /></div>
        <x-form.textarea name="notes" label="Supplier notes" :value="$supplier->notes" col="col-12" />
    </div>
    <div class="card-footer text-end"><a href="{{ url()->previous() }}" class="btn btn-light">Cancel</a> <button class="btn btn-primary">Save</button></div>
</form>
@endsection
