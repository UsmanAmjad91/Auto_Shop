@extends('layouts.app')
@section('title', $employee->full_name)
@section('content')
@php $u = auth()->user(); @endphp
<x-page-header :title="$employee->full_name" :breadcrumbs="['Employees' => route('employees.index'), $employee->employee_code => null]">
    @can('tasks.create')<button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#taskModal"><i class="bi bi-list-check"></i> Assign task</button>@endcan
    @can('employees.edit')<a href="{{ route('employees.edit', $employee) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>@endcan
</x-page-header>
<div class="row g-3 mb-3">
    <div class="col-lg-4"><div class="card h-100"><div class="card-body text-center">
        @if($employee->photoUrl())<img src="{{ $employee->photoUrl() }}" class="rounded-circle mb-2" width="90" height="90" style="object-fit:cover" alt="">@else<div class="display-4 text-secondary"><i class="bi bi-person-circle"></i></div>@endif
        <h5 class="mb-0">{{ $employee->full_name }}</h5><div class="text-secondary">{{ $employee->job_title }}</div><x-status :status="$employee->employment_status" />
    </div><dl class="dl-grid card-body border-top">
        <dt>ID</dt><dd>{{ $employee->employee_code }}</dd><dt>Department</dt><dd>{{ $employee->department?->name ?? '—' }}</dd>
        <dt>Manager</dt><dd>{{ $employee->manager?->full_name ?? '—' }}</dd><dt>Hired</dt><dd>{{ fmt_date($employee->hire_date) ?: '—' }}</dd>
        <dt>Type</dt><dd>{{ status_label($employee->employment_type) }}</dd>
        <dt>Phone</dt><dd>{{ $employee->phone ?: '—' }}</dd><dt>Email</dt><dd>{{ $employee->email ?: '—' }}</dd><dt>Address</dt><dd>{{ $employee->address ?: '—' }}</dd>
        <dt>Emergency</dt><dd>{{ $employee->emergency_contact_name }} {{ $employee->emergency_contact_phone }} {{ $employee->emergency_contact_relation ? '('.$employee->emergency_contact_relation.')' : '' }}</dd>
        <dt>User account</dt><dd>@if($employee->user)@can('users.view')<a href="{{ route('users.show', $employee->user) }}">{{ $employee->user->email }}</a>@else{{ $employee->user->email }}@endcan @else — @endif</dd>
        <dt>Schedule</dt><dd class="small">@forelse($employee->work_schedule ?? [] as $d => $t){{ ucfirst($d) }} {{ $t['start'] }}–{{ $t['end'] }}<br>@empty — @endforelse</dd>
    </dl>@if($employee->notes)<div class="card-body border-top small">{{ $employee->notes }}</div>@endif</div></div>
    <div class="col-lg-8">
        @can('employees.view_pay')
        <div class="card mb-3"><div class="card-header">Pay rate history <span class="small text-secondary fw-normal">(historical rates are preserved for payroll)</span></div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Type</th><th class="text-end">Rate</th><th>OT ×</th><th>Effective</th><th>Reason</th></tr></thead>
                <tbody>@forelse($rates as $r)<tr class="{{ $r->effective_to ? 'text-secondary' : 'fw-semibold' }}"><td>{{ config('autoshop.enums.pay_types.'.$r->pay_type) }}</td><td class="text-end money">{{ money($r->rate) }}</td><td>{{ $r->overtime_multiplier }}</td>
                    <td>{{ fmt_date($r->effective_from) }} → {{ $r->effective_to ? fmt_date($r->effective_to) : 'current' }}</td><td class="small">{{ $r->reason }}</td></tr>
                @empty<tr><td colspan="5" class="text-secondary small">No pay rate set — payroll cannot be generated for this employee.</td></tr>@endforelse</tbody></table></div>
            @can('employees.edit')<form method="POST" action="{{ route('employees.pay-rates.store', $employee) }}" class="card-footer row g-2">@csrf
                <div class="col-md-3"><select name="pay_type" class="form-select form-select-sm">@foreach(config('autoshop.enums.pay_types') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="col-md-2"><input name="rate" type="number" step="0.01" min="0" class="form-control form-control-sm" placeholder="Rate" required></div>
                <div class="col-md-2"><input name="overtime_multiplier" type="number" step="0.05" min="1" value="{{ settings('payroll.overtime_multiplier', 1.5) }}" class="form-control form-control-sm" title="OT multiplier"></div>
                <div class="col-md-2"><input name="effective_from" type="date" value="{{ today()->toDateString() }}" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><input name="reason" class="form-control form-control-sm" placeholder="Reason"></div>
                <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Add</button></div>
            </form>@endcan
        </div>
        @endcan
        @include('partials.attachments', ['model' => $employee, 'type' => 'employee', 'module' => 'employees'])
    </div>
</div>
<ul class="nav nav-tabs mb-3">
    @can('attendance.view_all')<li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#att">Attendance (31 days)</a></li>@endcan
    @can('payroll.view')<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#payroll">Payroll</a></li><li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pays">Payments</a></li>@endcan
    @can('service_jobs.view_all')<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#jobs">Jobs</a></li>@endcan
    @can('tasks.view_all')<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tasks">Tasks</a></li>@endcan
</ul>
<div class="tab-content">
    @can('attendance.view_all')<div class="tab-pane fade show active" id="att"><div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Date</th><th>In</th><th>Out</th><th>Break</th><th class="text-end">Hours</th><th class="text-end">OT</th><th>Status</th></tr></thead>
        <tbody>@forelse($attendance as $a)<tr><td>{{ fmt_date($a->work_date) }}</td><td>{{ $a->clock_in?->format('H:i') }}</td><td>{{ $a->clock_out?->format('H:i') }}</td><td>{{ $a->break_minutes }}m</td>
            <td class="text-end">{{ $a->total_hours }}</td><td class="text-end">{{ $a->overtime_hours }}</td><td><x-status :status="$a->status" /></td></tr>
        @empty<tr><td colspan="7" class="text-secondary small">No attendance records.</td></tr>@endforelse</tbody></table></div></div></div>@endcan
    @can('payroll.view')
    <div class="tab-pane fade" id="payroll"><div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Period</th><th class="text-end">Gross</th><th class="text-end">Net</th><th class="text-end">Paid</th><th class="text-end">Remaining</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse($entries as $e)<tr><td><a href="{{ route('payroll.show', $e->payroll_period_id) }}">{{ $e->period->name }}</a></td><td class="text-end money">{{ money($e->gross_earnings) }}</td><td class="text-end money">{{ money($e->net_pay) }}</td>
            <td class="text-end money">{{ money($e->amount_paid) }}</td><td class="text-end money">{{ money($e->remaining_payable) }}</td><td><x-status :status="$e->payment_status" /></td>
            <td><a href="{{ route('payroll.entries.payslip', $e) }}" class="small">Payslip</a></td></tr>
        @empty<tr><td colspan="7" class="text-secondary small">No payroll entries.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="tab-pane fade" id="pays"><div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Date</th><th>Type</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th></tr></thead>
        <tbody>@forelse($payments as $p)<tr><td>{{ fmt_date($p->payment_date) }}</td><td>{{ status_label($p->type) }}</td><td>{{ status_label($p->method) }}</td><td>{{ $p->reference }}</td><td class="text-end money">{{ money($p->amount) }}</td></tr>
        @empty<tr><td colspan="5" class="text-secondary small">No payments.</td></tr>@endforelse</tbody></table></div></div></div>
    @endcan
    @can('service_jobs.view_all')<div class="tab-pane fade" id="jobs"><div class="card"><div class="list-group list-group-flush">@forelse($jobs as $j)
        <a href="{{ route('service-jobs.show', $j) }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span>{{ $j->job_number }} — {{ $j->title }} <span class="small text-secondary">{{ $j->vehicle?->title() }} · {{ $j->pivot->role }} · {{ $j->pivot->hours_worked }}h</span></span><x-status :status="$j->status" /></a>
    @empty<div class="list-group-item text-secondary small">No jobs.</div>@endforelse</div></div></div>@endcan
    @can('tasks.view_all')<div class="tab-pane fade" id="tasks"><div class="card"><div class="list-group list-group-flush">@forelse($tasks as $t)
        <div class="list-group-item d-flex justify-content-between"><span>{{ $t->title }} <span class="small text-secondary">due {{ fmt_date($t->due_date) ?: '—' }}</span></span><x-status :status="$t->status" /></div>
    @empty<div class="list-group-item text-secondary small">No tasks.</div>@endforelse</div></div></div>@endcan
</div>
@can('tasks.create')@include('tasks._modal', ['employeeId' => $employee->id, 'employeeName' => $employee->full_name])@endcan
@endsection
