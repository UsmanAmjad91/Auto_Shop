<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Services\AttendanceService;
use App\Support\DataTable;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendance) {}

    protected function table(Request $request): DataTable
    {
        $user = $request->user();
        $query = AttendanceRecord::with(['employee.department', 'corrector']);
        if (! $user->can('attendance.view_all')) {
            $query->where('employee_id', $user->employee?->id ?? 0); // record-level: own attendance only
        }

        return DataTable::make($query, $request)
            ->searchable(['employee.first_name', 'employee.last_name', 'employee.employee_code', 'notes'])
            ->sortable(['work_date' => 'work_date', 'total_hours' => 'total_hours', 'status' => 'status'], 'work_date')
            ->filters(fn ($q, $r) => $q->when($r->employee_id, fn ($x) => $x->where('employee_id', $r->employee_id))
                ->when($r->department_id, fn ($x) => $x->whereHas('employee', fn ($e) => $e->where('department_id', $r->department_id)))
                ->when($r->status, fn ($x) => $x->where('status', $r->status))
                ->when($r->date_from, fn ($x) => $x->whereDate('work_date', '>=', $r->date_from))
                ->when($r->date_to, fn ($x) => $x->whereDate('work_date', '<=', $r->date_to)));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $request->expectsJson()) {
            return view('attendance.index', [
                'departments' => Department::orderBy('name')->pluck('name', 'id'),
                'today' => $user->employee ? $this->attendance->today($user->employee) : null,
                'employee' => $user->employee,
            ]);
        }

        return $this->table($request)->transform(fn (AttendanceRecord $a) => [
            'id' => $a->id, 'date' => fmt_date($a->work_date), 'work_date' => $a->work_date->toDateString(), 'employee' => $a->employee?->full_name,
            'employee_id' => $a->employee_id, 'department' => $a->employee?->department?->name,
            'clock_in' => $a->clock_in?->format('H:i'), 'clock_out' => $a->clock_out?->format('H:i'),
            'break_start' => $a->break_start?->format('H:i'), 'break_end' => $a->break_end?->format('H:i'),
            'break_minutes' => $a->break_minutes, 'total_hours' => (float) $a->total_hours, 'overtime_hours' => (float) $a->overtime_hours,
            'status' => ['label' => status_label($a->status), 'color' => status_color($a->status)], 'status_raw' => $a->status,
            'source' => status_label($a->source), 'notes' => $a->notes,
            'corrected' => $a->corrected_at ? 'Corrected by '.$a->corrector?->name.' '.$a->corrected_at->diffForHumans() : '',
            'actions' => array_values(array_filter([
                $user->can('attendance.edit') ? ['label' => 'Correct', 'icon' => 'pencil', 'event' => 'attendance:edit'] : null,
                $user->can('attendance.delete') ? ['label' => 'Delete', 'icon' => 'trash', 'url' => route('attendance.destroy', $a), 'method' => 'DELETE', 'confirm' => 'Delete this record?', 'danger' => true] : null,
            ])),
        ])->response();
    }

    public function export(Request $request)
    {
        $rows = $this->table($request)->all()->map(fn ($a) => ['date' => $a->work_date->toDateString(), 'employee' => $a->employee?->full_name,
            'clock_in' => $a->clock_in?->format('H:i'), 'clock_out' => $a->clock_out?->format('H:i'), 'break' => $a->break_minutes,
            'total' => $a->total_hours, 'overtime' => $a->overtime_hours, 'status' => $a->status, 'notes' => $a->notes]);

        return Exporter::download($request->input('format', 'csv'), 'Attendance', ['date' => 'Date', 'employee' => 'Employee', 'clock_in' => 'Clock In',
            'clock_out' => 'Clock Out', 'break' => 'Break (min)', 'total' => 'Total Hours', 'overtime' => 'Overtime', 'status' => 'Status', 'notes' => 'Notes'], $rows);
    }

    /** Employee self-service punch. */
    public function punch(Request $request)
    {
        $request->validate(['action' => ['required', 'in:clock_in,break_start,break_end,clock_out']]);
        $employee = $request->user()->employee;
        abort_unless($employee, 422, 'Your user account is not linked to an employee profile.');
        $record = $this->attendance->punch($employee, $request->action);

        return $this->done($request, status_label($request->action).' recorded at '.now()->format('H:i').'.', null, ['record' => $record]);
    }

    public function store(Request $request)
    {
        $this->attendance->saveManual($this->validated($request));

        return $this->done($request, 'Attendance saved.');
    }

    public function update(Request $request, AttendanceRecord $record)
    {
        $this->authorize('update', $record);
        $data = $this->validated($request);
        $data['employee_id'] = $record->employee_id;
        $this->attendance->saveManual($data, $record);

        return $this->done($request, 'Attendance corrected (change recorded in audit log).');
    }

    public function destroy(Request $request, AttendanceRecord $record)
    {
        $this->authorize('delete', $record);
        $record->delete();

        return $this->done($request, 'Attendance record deleted.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'clock_in' => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'break_start' => ['nullable', 'date_format:H:i'],
            'break_end' => ['nullable', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'status' => ['required', Rule::in(array_keys(config('autoshop.enums.attendance_statuses')))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    public function bulk(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'], 'action' => ['required', 'in:delete']]);
        abort_unless($request->user()->can('attendance.delete'), 403);
        $n = AttendanceRecord::whereIn('id', $data['ids'])->get()->each->delete()->count();

        return $this->done($request, "{$n} record(s) deleted.");
    }
}
