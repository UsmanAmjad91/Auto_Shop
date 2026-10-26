@extends('layouts.app')
@section('title', 'Payroll payments')
@section('content')
<x-page-header title="Employee payment history" :breadcrumbs="['Payroll' => route('payroll.index'), 'Payments' => null]" />
<x-filters for="emp-payments-table">
    <x-form.remote name="employee_id" type="employees" label="Employee" col="col-6 col-md-3" />
    <x-form.select name="method" label="Method" :options="['bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'check' => 'Check', 'other' => 'Other']" placeholder="Any" col="col-6 col-md-3" />
    <div class="col-6 col-md-4"><label class="form-label">Dates</label><div class="input-group input-group-sm"><input type="date" name="date_from" class="form-control"><input type="date" name="date_to" class="form-control"></div></div>
</x-filters>
<x-datatable id="emp-payments-table" :url="route('payroll.payments')" module="employee_payments" sort="payment_date" :columns="[
    ['key' => 'date', 'label' => 'Date', 'sortable' => true, 'sort' => 'payment_date'],
    ['key' => 'employee', 'label' => 'Employee'],
    ['key' => 'period', 'label' => 'Period', 'type' => 'link'],
    ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'sortable' => true],
    ['key' => 'method', 'label' => 'Method'],
    ['key' => 'reference', 'label' => 'Reference'],
    ['key' => 'by', 'label' => 'Paid by'],
]" />
@endsection
