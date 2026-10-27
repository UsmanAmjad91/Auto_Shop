@extends('layouts.app')
@section('title', 'Employees')
@section('content')
<x-page-header title="Employees" :breadcrumbs="['Dashboard' => route('dashboard'), 'Employees' => null]">
    @can('employees.create')<a href="{{ route('employees.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Add employee</a>@endcan
</x-page-header>
<x-filters for="employees-table">
    <x-form.select name="department_id" label="Department" :options="$departments" placeholder="All" col="col-6 col-md-3" />
    <x-form.select name="employment_status" label="Status" :options="config('autoshop.enums.employment_statuses')" placeholder="Current (not terminated)" col="col-6 col-md-3" />
    <div class="col-12 col-md-4"><label class="form-label">Hire date</label><div class="input-group input-group-sm"><input type="date" name="date_from" class="form-control"><input type="date" name="date_to" class="form-control"></div></div>
</x-filters>
<x-datatable id="employees-table" :url="route('employees.index')" module="employees" sort="name" dir="asc" :bulk-url="route('employees.bulk')"
    :bulk="auth()->user()->can('employees.edit') ? [['action' => 'department', 'label' => 'Move to department', 'options' => $departments->all()], ['action' => 'status', 'label' => 'Set status', 'options' => config('autoshop.enums.employment_statuses')]] : []"
    :export-url="auth()->user()->can('employees.export') ? route('employees.export') : null"
    :columns="array_values(array_filter([
        ['key' => 'photo', 'label' => '', 'type' => 'image'],
        ['key' => 'code', 'label' => 'ID', 'sortable' => true, 'sort' => 'employee_code'],
        ['key' => 'name', 'label' => 'Name', 'type' => 'link', 'sortable' => true],
        ['key' => 'job_title', 'label' => 'Job title', 'sortable' => true],
        ['key' => 'department', 'label' => 'Department'],
        ['key' => 'phone', 'label' => 'Phone'],
        ['key' => 'email', 'label' => 'Email', 'hidden' => true],
        ['key' => 'manager', 'label' => 'Manager', 'hidden' => true],
        ['key' => 'hire_date', 'label' => 'Hired', 'sortable' => true],
        auth()->user()->can('employees.view_pay') ? ['key' => 'pay', 'label' => 'Current pay'] : null,
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['key' => 'actions', 'label' => '', 'type' => 'actions'],
    ]))" />
@endsection
