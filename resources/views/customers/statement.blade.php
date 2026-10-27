@extends('layouts.app')
@section('title', 'Statement')
@section('content')
<x-page-header title="Customer statement" :breadcrumbs="['Customers' => route('customers.index'), $customer->code => route('customers.show', $customer), 'Statement' => null]">
    <a href="{{ route('customers.statement', [$customer, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'pdf' => 1]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-pdf"></i> PDF</a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
</x-page-header>
<form class="card card-body mb-3 no-print row g-2 flex-row">
    <div class="col-auto"><label class="form-label small">From</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><label class="form-label small">To</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
    <div class="col-auto align-self-end"><button class="btn btn-sm btn-primary">Update</button></div>
</form>
<div class="card card-body">@include('customers._statement')</div>
@endsection
