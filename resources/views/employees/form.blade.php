@extends('layouts.app')
@section('title', $employee->exists ? 'Edit employee' : 'Add employee')
@section('content')
<x-page-header :title="$employee->exists ? 'Edit '.$employee->full_name : 'Add employee'" :breadcrumbs="['Employees' => route('employees.index'), ($employee->exists ? $employee->employee_code : 'Add') => null]" />
<form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" enctype="multipart/form-data">
    @csrf @if($employee->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3"><div class="card-header">Personal & contact</div><div class="card-body row g-3">
                <x-form.input name="first_name" label="First name" :value="$employee->first_name" required col="col-md-4" />
                <x-form.input name="last_name" label="Last name" :value="$employee->last_name" col="col-md-4" />
                <x-form.input name="employee_code" label="Employee ID" :value="$employee->employee_code" col="col-md-4" help="Blank = auto" />
                <x-form.input name="phone" label="Phone" :value="$employee->phone" col="col-md-4" />
                <x-form.input name="email" type="email" label="Email" :value="$employee->email" col="col-md-4" />
                <x-form.input name="photo" type="file" label="Photo" accept="image/*" col="col-md-4" />
                <x-form.input name="address" label="Address" :value="$employee->address" col="col-12" />
                <x-form.input name="emergency_contact_name" label="Emergency contact" :value="$employee->emergency_contact_name" col="col-md-4" />
                <x-form.input name="emergency_contact_phone" label="Emergency phone" :value="$employee->emergency_contact_phone" col="col-md-4" />
                <x-form.input name="emergency_contact_relation" label="Relationship" :value="$employee->emergency_contact_relation" col="col-md-4" />
            </div></div>
            <div class="card mb-3"><div class="card-header">Work schedule</div><div class="card-body">
                <div class="row g-2">
                @foreach(['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'] as $d => $label)
                    <div class="col-md-6 d-flex align-items-center gap-2"><span class="small" style="width:90px">{{ $label }}</span>
                        <input type="time" name="work_schedule[{{ $d }}][start]" value="{{ old("work_schedule.$d.start", $employee->work_schedule[$d]['start'] ?? '') }}" class="form-control form-control-sm" aria-label="{{ $label }} start">
                        <span>–</span><input type="time" name="work_schedule[{{ $d }}][end]" value="{{ old("work_schedule.$d.end", $employee->work_schedule[$d]['end'] ?? '') }}" class="form-control form-control-sm" aria-label="{{ $label }} end"></div>
                @endforeach
                </div><div class="form-text">Used to flag late clock-ins. Leave blank for days off.</div>
            </div></div>
            <x-form.textarea name="notes" label="Notes" :value="$employee->notes" />
        </div>
        <div class="col-xl-4">
            <div class="card mb-3"><div class="card-header">Employment</div><div class="card-body row g-3">
                <x-form.input name="job_title" label="Job title" :value="$employee->job_title" col="col-12" />
                <x-form.select name="department_id" label="Department" :options="$departments" :value="$employee->department_id" placeholder="—" col="col-12" />
                <x-form.select name="manager_id" label="Manager / supervisor" :options="$managers" :value="$employee->manager_id" placeholder="—" col="col-12" searchable />
                <x-form.input name="hire_date" type="date" label="Hire date" :value="$employee->hire_date" col="col-6" />
                <x-form.input name="termination_date" type="date" label="End date" :value="$employee->termination_date" col="col-6" />
                <x-form.select name="employment_status" label="Status" :options="config('autoshop.enums.employment_statuses')" :value="$employee->employment_status" col="col-6" />
                <x-form.select name="employment_type" label="Type" :options="['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'temporary' => 'Temporary']" :value="$employee->employment_type" col="col-6" />
                <x-form.select name="user_id" label="Linked user account" :options="$users" :value="$employee->user_id" placeholder="— none —" col="col-12" searchable help="Enables self-service attendance and payslips" />
            </div></div>
            @if(! $employee->exists && auth()->user()->can('employees.view_pay'))
            <div class="card mb-3"><div class="card-header">Initial pay rate</div><div class="card-body row g-3">
                <x-form.select name="pay_type" label="Pay type" :options="config('autoshop.enums.pay_types')" placeholder="—" col="col-6" />
                <x-form.input name="rate" type="number" step="0.01" min="0" label="Rate" col="col-6" />
                <x-form.input name="overtime_multiplier" type="number" step="0.05" min="1" label="Overtime multiplier" :value="settings('payroll.overtime_multiplier', 1.5)" col="col-6" />
            </div></div>
            @endif
            <div class="d-grid gap-2"><button class="btn btn-primary btn-lg">Save employee</button></div>
        </div>
    </div>
</form>
@endsection
