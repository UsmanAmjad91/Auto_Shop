@extends('layouts.app')
@section('title', 'Attendance')
@section('content')
@php $u = auth()->user(); @endphp
<x-page-header title="Attendance" :breadcrumbs="['Dashboard' => route('dashboard'), 'Attendance' => null]">
    @can('attendance.edit')<button class="btn btn-primary btn-sm" id="attendance-add"><i class="bi bi-plus-lg"></i> Add / correct record</button>@endcan
</x-page-header>

@if($employee && $u->can('attendance.create'))
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
    <div class="me-auto"><div class="fw-semibold">{{ $employee->full_name }} — {{ now()->format('l, '.settings('general.date_format', 'Y-m-d')) }}</div>
        <div class="small text-secondary">
            In: <strong>{{ $today?->clock_in?->format('H:i') ?? '—' }}</strong> · Break: <strong>{{ $today?->break_start?->format('H:i') ?? '—' }}–{{ $today?->break_end?->format('H:i') ?? '' }}</strong>
            ({{ $today->break_minutes ?? 0 }} min) · Out: <strong>{{ $today?->clock_out?->format('H:i') ?? '—' }}</strong> @if($today?->total_hours > 0)· {{ $today->total_hours }} h @endif
            @if($today)<x-status :status="$today->status" />@endif
        </div></div>
    <button class="btn btn-success" data-punch="clock_in" @disabled($today?->clock_in)><i class="bi bi-box-arrow-in-right"></i> Clock in</button>
    <button class="btn btn-outline-warning" data-punch="break_start" @disabled(! $today?->clock_in || $today?->clock_out || ($today?->break_start && ! $today?->break_end))>Start break</button>
    <button class="btn btn-outline-warning" data-punch="break_end" @disabled(! $today?->break_start || $today?->break_end || $today?->clock_out)>End break</button>
    <button class="btn btn-danger" data-punch="clock_out" @disabled(! $today?->clock_in || $today?->clock_out)><i class="bi bi-box-arrow-right"></i> Clock out</button>
</div></div>
@endif

<x-filters for="attendance-table">
    @can('attendance.view_all')
        <x-form.remote name="employee_id" type="employees" label="Employee" col="col-6 col-md-3" />
        <x-form.select name="department_id" label="Department" :options="$departments" placeholder="All" col="col-6 col-md-2" />
    @endcan
    <x-form.select name="status" label="Status" :options="config('autoshop.enums.attendance_statuses')" placeholder="Any" col="col-6 col-md-2" />
    <div class="col-6 col-md-4"><label class="form-label">Date range</label><div class="input-group input-group-sm"><input type="date" name="date_from" class="form-control"><input type="date" name="date_to" class="form-control"></div></div>
</x-filters>
<x-datatable id="attendance-table" :url="route('attendance.index')" module="attendance" sort="work_date" :bulk-url="route('attendance.bulk')"
    :bulk="$u->can('attendance.delete') ? [['action' => 'delete', 'label' => 'Delete selected', 'confirm' => 'Delete :count record(s)?', 'danger' => true]] : []"
    :export-url="$u->can('attendance.export') ? route('attendance.export') : null" :columns="[
    ['key' => 'date', 'label' => 'Date', 'sortable' => true, 'sort' => 'work_date'],
    ['key' => 'employee', 'label' => 'Employee'],
    ['key' => 'department', 'label' => 'Department', 'hidden' => true],
    ['key' => 'clock_in', 'label' => 'In'],
    ['key' => 'break_start', 'label' => 'Break start', 'hidden' => true],
    ['key' => 'break_end', 'label' => 'Break end', 'hidden' => true],
    ['key' => 'break_minutes', 'label' => 'Break (min)'],
    ['key' => 'clock_out', 'label' => 'Out'],
    ['key' => 'total_hours', 'label' => 'Hours', 'sortable' => true],
    ['key' => 'overtime_hours', 'label' => 'Overtime'],
    ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'sortable' => true],
    ['key' => 'source', 'label' => 'Source', 'hidden' => true],
    ['key' => 'corrected', 'label' => 'Correction'],
    ['key' => 'notes', 'label' => 'Notes', 'hidden' => true],
    ['key' => 'actions', 'label' => '', 'type' => 'actions'],
]" />

@can('attendance.edit')
@push('modals')
<x-modal id="attendanceModal" title="Attendance record">
    <form method="POST" action="{{ route('attendance.store') }}" data-store-url="{{ route('attendance.store') }}" data-ajax data-table-reload="#attendance-table">@csrf
        <input type="hidden" name="_method" value="POST">
        <div class="row g-2">
            <x-form.remote name="employee_id" type="employees" label="Employee" required col="col-12" id="att-employee" />
            <x-form.input name="work_date" type="date" label="Date" :value="today()" required col="col-md-6" />
            <x-form.select name="status" label="Status" :options="config('autoshop.enums.attendance_statuses')" col="col-md-6" />
            <x-form.input name="clock_in" type="time" label="Clock in" col="col-6" />
            <x-form.input name="clock_out" type="time" label="Clock out" col="col-6" />
            <x-form.input name="break_start" type="time" label="Break start" col="col-6" />
            <x-form.input name="break_end" type="time" label="Break end" col="col-6" />
            <x-form.input name="break_minutes" type="number" min="0" label="Break minutes (total)" col="col-6" />
            <x-form.input name="notes" label="Reason / notes" col="col-6" />
        </div>
        <div class="form-text">Corrections are stamped with your name and fully recorded in the audit log.</div>
        <div class="text-end mt-3"><button class="btn btn-primary">Save</button></div>
    </form>
</x-modal>
@endpush
@endcan
@endsection
