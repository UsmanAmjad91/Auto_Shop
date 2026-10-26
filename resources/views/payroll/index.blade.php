@extends('layouts.app')
@section('title', 'Payroll')
@section('content')
<x-page-header title="Payroll" :breadcrumbs="['Dashboard' => route('dashboard'), 'Payroll' => null]">
    <a href="{{ route('payroll.payments') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history"></i> Payment history</a>
    @can('payroll.create')<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#periodModal"><i class="bi bi-plus-lg"></i> New payroll period</button>@endcan
</x-page-header>
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> Payroll here is calculated from attendance, historical pay rates and your configured overtime rules. It does not compute statutory tax withholdings or jurisdiction-specific labour rules — enter those as deductions and confirm with your accountant / payroll provider.</div>
<x-filters for="payroll-table">
    <x-form.select name="status" label="Status" :options="['draft' => 'Draft', 'approved' => 'Approved', 'closed' => 'Closed']" placeholder="Any" col="col-6 col-md-3" />
    <div class="col-6 col-md-4"><label class="form-label">Period</label><div class="input-group input-group-sm"><input type="date" name="date_from" class="form-control"><input type="date" name="date_to" class="form-control"></div></div>
</x-filters>
<x-datatable id="payroll-table" :url="route('payroll.index')" module="payroll" sort="start_date" :columns="[
    ['key' => 'name', 'label' => 'Period', 'type' => 'link'],
    ['key' => 'frequency', 'label' => 'Frequency'],
    ['key' => 'period', 'label' => 'Dates', 'sortable' => true, 'sort' => 'start_date'],
    ['key' => 'pay_date', 'label' => 'Pay date'],
    ['key' => 'employees', 'label' => 'Employees'],
    ['key' => 'gross', 'label' => 'Gross', 'type' => 'money'],
    ['key' => 'net', 'label' => 'Net', 'type' => 'money', 'sortable' => true, 'sort' => 'total_net'],
    ['key' => 'paid', 'label' => 'Paid', 'type' => 'money'],
    ['key' => 'remaining', 'label' => 'Payable', 'type' => 'money'],
    ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ['key' => 'actions', 'label' => '', 'type' => 'actions'],
]" />
@can('payroll.create')
@push('modals')
<x-modal id="periodModal" title="New payroll period">
    <form method="POST" action="{{ route('payroll.store') }}">@csrf
        <div class="row g-2">
            <x-form.select name="frequency" label="Frequency" :options="['weekly' => 'Weekly', 'biweekly' => 'Biweekly', 'monthly' => 'Monthly', 'custom' => 'Custom']" value="monthly" col="col-md-6" />
            <x-form.input name="name" label="Name (optional)" col="col-md-6" />
            <x-form.input name="start_date" type="date" label="Start" :value="now()->startOfMonth()" required col="col-md-4" />
            <x-form.input name="end_date" type="date" label="End" :value="now()->endOfMonth()" required col="col-md-4" />
            <x-form.input name="pay_date" type="date" label="Pay date" :value="now()->endOfMonth()" col="col-md-4" />
        </div>
        <div class="form-text">Entries are generated for every current employee with a pay rate, using attendance in the period.</div>
        <div class="text-end mt-3"><button class="btn btn-primary">Create & generate</button></div>
    </form>
</x-modal>
@endpush
@endcan
@endsection
