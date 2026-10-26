<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeTask;
use App\Notifications\TaskAssigned;
use App\Support\DataTable;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $request->expectsJson()) {
            return view('tasks.index');
        }
        $query = EmployeeTask::with(['employee', 'vehicle', 'serviceJob']);
        if (! $user->can('tasks.view_all') || $request->boolean('mine')) {
            $query->where('employee_id', $user->employee?->id ?? 0);
        }

        return DataTable::make($query, $request)
            ->searchable(['title', 'description'])
            ->sortable(['due_date' => 'due_date', 'priority' => 'priority', 'created_at' => 'created_at', 'status' => 'status'], 'due_date', 'asc')
            ->filters(fn ($q, $r) => $q->when($r->status, fn ($x) => $x->where('status', $r->status), fn ($x) => $x->whereIn('status', ['open', 'in_progress']))
                ->when($r->priority, fn ($x) => $x->where('priority', $r->priority))
                ->when($r->employee_id, fn ($x) => $x->where('employee_id', $r->employee_id)))
            ->transform(fn (EmployeeTask $t) => [
                'id' => $t->id, 'title' => $t->title, 'description' => $t->description, 'employee' => $t->employee?->full_name,
                'vehicle' => $t->vehicle ? $t->vehicle->stock_number.' '.$t->vehicle->title() : null,
                'vehicle_url' => $t->vehicle ? route('vehicles.show', $t->vehicle_id) : null,
                'job' => $t->serviceJob?->job_number, 'due_date' => fmt_date($t->due_date),
                'overdue' => $t->due_date && $t->due_date->isPast() && ! in_array($t->status, ['done', 'cancelled'], true),
                'priority' => ['label' => ucfirst($t->priority), 'color' => status_color($t->priority === 'urgent' ? 'urgent' : ($t->priority === 'high' ? 'pending' : 'secondary'))],
                'status' => ['label' => status_label($t->status), 'color' => status_color($t->status)],
                'actions' => array_values(array_filter([
                    $user->can('update', $t) && $t->status !== 'done' ? ['label' => 'Mark done', 'icon' => 'check2', 'url' => route('tasks.update', $t), 'method' => 'PUT', 'data' => ['status' => 'done']] : null,
                    $user->can('update', $t) && $t->status === 'open' ? ['label' => 'Start', 'icon' => 'play', 'url' => route('tasks.update', $t), 'method' => 'PUT', 'data' => ['status' => 'in_progress']] : null,
                    $user->can('delete', $t) ? ['label' => 'Delete', 'icon' => 'trash', 'url' => route('tasks.destroy', $t), 'method' => 'DELETE', 'confirm' => 'Delete task?', 'danger' => true] : null,
                ])),
            ])->response();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'employee_id' => ['required', 'exists:employees,id'], 'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'service_job_id' => ['nullable', 'exists:service_jobs,id'], 'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(array_keys(config('autoshop.enums.priorities')))],
        ]);
        $task = EmployeeTask::create($data + ['status' => 'open', 'assigned_by' => $request->user()->id]);
        if ($user = Employee::find($data['employee_id'])?->user) {
            $user->notify(new TaskAssigned('New task assigned', $task->title.($task->due_date ? ' (due '.fmt_date($task->due_date).')' : ''), route('tasks.index', ['mine' => 1])));
        }

        return $this->done($request, 'Task assigned.');
    }

    public function update(Request $request, EmployeeTask $task)
    {
        $this->authorize('update', $task);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(EmployeeTask::STATUSES))]]);
        $task->update($data + ['completed_at' => $data['status'] === 'done' ? now() : null]);

        return $this->done($request, 'Task updated.');
    }

    public function destroy(Request $request, EmployeeTask $task)
    {
        $this->authorize('delete', $task);
        $task->delete();

        return $this->done($request, 'Task deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'], 'action' => ['required', 'in:done,cancelled,delete']]);
        $n = 0;
        foreach (EmployeeTask::whereIn('id', $data['ids'])->get() as $task) {
            if ($data['action'] === 'delete') {
                if ($request->user()->can('delete', $task)) {
                    $task->delete();
                    $n++;
                }
            } elseif ($request->user()->can('update', $task)) {
                $task->update(['status' => $data['action'], 'completed_at' => $data['action'] === 'done' ? now() : null]);
                $n++;
            }
        }

        return $this->done($request, "{$n} task(s) updated.");
    }

    public function export(Request $request)
    {
        $user = $request->user();
        $rows = EmployeeTask::with(['employee', 'vehicle', 'serviceJob'])
            ->when(! $user->can('tasks.view_all'), fn ($q) => $q->where('employee_id', $user->employee?->id ?? 0))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('due_date')->limit(20000)->get()
            ->map(fn ($t) => ['title' => $t->title, 'employee' => $t->employee?->full_name, 'vehicle' => $t->vehicle?->stock_number,
                'job' => $t->serviceJob?->job_number, 'due' => $t->due_date?->toDateString(), 'priority' => $t->priority, 'status' => $t->status]);

        return Exporter::download($request->input('format', 'csv'), 'Tasks', ['title' => 'Task', 'employee' => 'Employee', 'vehicle' => 'Vehicle',
            'job' => 'Job', 'due' => 'Due', 'priority' => 'Priority', 'status' => 'Status'], $rows);
    }
}
