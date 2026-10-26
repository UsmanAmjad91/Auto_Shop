@extends('layouts.app')
@section('title', 'Tasks')
@section('content')
<x-page-header title="Employee tasks" :breadcrumbs="['Dashboard' => route('dashboard'), 'Tasks' => null]">
    @can('tasks.create')<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#taskModal"><i class="bi bi-plus-lg"></i> Assign task</button>@endcan
</x-page-header>
<x-filters for="tasks-table">
    <x-form.select name="status" label="Status" :options="\App\Models\EmployeeTask::STATUSES" placeholder="Open & in progress" col="col-6 col-md-3" />
    <x-form.select name="priority" label="Priority" :options="config('autoshop.enums.priorities')" placeholder="Any" col="col-6 col-md-3" />
    @can('tasks.view_all')
        <x-form.remote name="employee_id" type="employees" label="Employee" col="col-6 col-md-3" />
        <div class="col-6 col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mine"><label class="form-check-label small" for="mine">Only my tasks</label></div></div>
    @endcan
</x-filters>
<x-datatable id="tasks-table" :url="route('tasks.index')" module="tasks" sort="due_date" dir="asc" :export-url="route('tasks.export')" :bulk-url="route('tasks.bulk')"
    :bulk="array_values(array_filter([auth()->user()->can('tasks.edit') ? ['action' => 'done', 'label' => 'Mark done'] : null, auth()->user()->can('tasks.edit') ? ['action' => 'cancelled', 'label' => 'Cancel'] : null,
        auth()->user()->can('tasks.delete') ? ['action' => 'delete', 'label' => 'Delete', 'confirm' => 'Delete :count task(s)?', 'danger' => true] : null]))" :columns="[
    ['key' => 'title', 'label' => 'Task'],
    ['key' => 'employee', 'label' => 'Assigned to'],
    ['key' => 'vehicle', 'label' => 'Vehicle'],
    ['key' => 'job', 'label' => 'Job'],
    ['key' => 'due_date', 'label' => 'Due', 'sortable' => true],
    ['key' => 'priority', 'label' => 'Priority', 'type' => 'badge', 'sortable' => true],
    ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'sortable' => true],
    ['key' => 'actions', 'label' => '', 'type' => 'actions'],
]" />
@can('tasks.create')@include('tasks._modal')@endcan
@endsection
