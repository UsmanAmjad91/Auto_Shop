<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePayRate;
use App\Models\User;
use App\Support\DataTable;
use App\Support\Exporter;
use App\Support\NumberGenerator;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    protected function table(Request $request): DataTable
    {
        return DataTable::make(Employee::query()->with(['department', 'manager']), $request)
            ->searchable(['employee_code', 'first_name', 'last_name', 'email', 'phone', 'job_title'])
            ->sortable(['name' => 'first_name', 'employee_code' => 'employee_code', 'hire_date' => 'hire_date', 'job_title' => 'job_title'], 'first_name', 'asc')
            ->filters(fn ($q, $r) => $q->when($r->department_id, fn ($x) => $x->where('department_id', $r->department_id))
                ->when($r->employment_status, fn ($x) => $x->where('employment_status', $r->employment_status),
                    fn ($x) => $x->where('employment_status', '!=', 'terminated'))
                ->when($r->date_from, fn ($x) => $x->whereDate('hire_date', '>=', $r->date_from))
                ->when($r->date_to, fn ($x) => $x->whereDate('hire_date', '<=', $r->date_to)));
    }

    public function index(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('employees.index', ['departments' => Department::orderBy('name')->pluck('name', 'id')]);
        }
        $user = $request->user();
        $pay = $user->can('employees.view_pay');

        return $this->table($request)->transform(function (Employee $e) use ($user, $pay) {
            $rate = $pay ? $e->currentPayRate() : null;

            return [
                'id' => $e->id, 'url' => route('employees.show', $e), 'photo' => $e->photoUrl(), 'code' => $e->employee_code, 'name' => $e->full_name,
                'job_title' => $e->job_title, 'department' => $e->department?->name, 'phone' => $e->phone, 'email' => $e->email,
                'hire_date' => fmt_date($e->hire_date), 'manager' => $e->manager?->full_name,
                'pay' => $rate ? money($rate->rate).' / '.config('autoshop.enums.pay_types.'.$rate->pay_type) : ($pay ? '—' : null),
                'status' => ['label' => status_label($e->employment_status), 'color' => status_color($e->employment_status)],
                'actions' => array_values(array_filter([
                    ['label' => 'View', 'icon' => 'eye', 'url' => route('employees.show', $e)],
                    $user->can('employees.edit') ? ['label' => 'Edit', 'icon' => 'pencil', 'url' => route('employees.edit', $e)] : null,
                    $user->can('employees.delete') ? ['label' => 'Delete', 'icon' => 'trash', 'url' => route('employees.destroy', $e), 'method' => 'DELETE', 'confirm' => 'Delete employee?', 'danger' => true] : null,
                ])),
            ];
        })->response();
    }

    public function export(Request $request)
    {
        $pay = $request->user()->can('employees.view_pay');
        $rows = $this->table($request)->all()->map(function (Employee $e) use ($pay) {
            $rate = $pay ? $e->currentPayRate() : null;

            return ['code' => $e->employee_code, 'name' => $e->full_name, 'job_title' => $e->job_title, 'department' => $e->department?->name,
                'phone' => $e->phone, 'email' => $e->email, 'hire_date' => fmt_date($e->hire_date), 'status' => $e->employment_status,
                'pay_type' => $rate?->pay_type, 'rate' => $rate?->rate];
        });
        $cols = ['code' => 'Employee ID', 'name' => 'Name', 'job_title' => 'Job Title', 'department' => 'Department', 'phone' => 'Phone',
            'email' => 'Email', 'hire_date' => 'Hire Date', 'status' => 'Status'] + ($pay ? ['pay_type' => 'Pay Type', 'rate' => 'Rate'] : []);

        return Exporter::download($request->input('format', 'csv'), 'Employees', $cols, $rows);
    }

    public function create()
    {
        return view('employees.form', $this->formData(new Employee(['employment_status' => 'active', 'employment_type' => 'full_time', 'hire_date' => today()])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $employee = DB::transaction(function () use ($request, $data) {
            $employee = new Employee(collect($data)->except(['photo', 'pay_type', 'rate', 'overtime_multiplier'])->all());
            $employee->employee_code = $data['employee_code'] ?? null ?: NumberGenerator::next('employee', settings('general.employee_prefix', 'EMP-'), 4);
            if ($request->hasFile('photo')) {
                $employee->photo = Uploads::image($request->file('photo'), 'employees');
            }
            $employee->save();
            if ($request->user()->can('employees.view_pay') && isset($data['rate'])) {
                $employee->payRates()->create(['pay_type' => $data['pay_type'], 'rate' => $data['rate'],
                    'overtime_multiplier' => $data['overtime_multiplier'] ?? settings('payroll.overtime_multiplier', 1.5),
                    'effective_from' => $employee->hire_date ?? today(), 'reason' => 'Initial rate', 'created_by' => $request->user()->id]);
            }

            return $employee;
        });

        return redirect()->route('employees.show', $employee)->with('success', 'Employee created.');
    }

    public function show(Request $request, Employee $employee)
    {
        $user = $request->user();

        return view('employees.show', [
            'employee' => $employee->load(['department', 'manager', 'user', 'attachments']),
            'rates' => $user->can('employees.view_pay') ? $employee->payRates()->with('employee')->get() : collect(),
            'attendance' => $user->can('attendance.view_all') ? $employee->attendance()->latest('work_date')->limit(31)->get() : collect(),
            'entries' => $user->can('payroll.view') ? $employee->payrollEntries()->with('period')->latest('id')->limit(24)->get() : collect(),
            'payments' => $user->can('payroll.view') ? $employee->payments()->latest('payment_date')->limit(50)->get() : collect(),
            'jobs' => $user->can('service_jobs.view_all') ? $employee->serviceJobs()->with('vehicle')->latest('service_jobs.created_at')->limit(30)->get() : collect(),
            'tasks' => $user->can('tasks.view_all') ? $employee->tasks()->latest()->limit(30)->get() : collect(),
        ]);
    }

    public function edit(Employee $employee)
    {
        return view('employees.form', $this->formData($employee));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validated($request, $employee);
        $employee->fill(collect($data)->except(['photo', 'pay_type', 'rate', 'overtime_multiplier', 'employee_code'])->all());
        if (! empty($data['employee_code'])) {
            $employee->employee_code = $data['employee_code'];
        }
        if ($request->hasFile('photo')) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $employee->photo = Uploads::image($request->file('photo'), 'employees');
        }
        $employee->save();

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated.');
    }

    public function destroy(Request $request, Employee $employee)
    {
        if ($employee->payrollEntries()->where('remaining_payable', '>', 0)->exists()) {
            return back()->withErrors(['employee' => 'This employee has unpaid payroll. Settle it or mark the employee terminated instead.']);
        }
        $employee->delete();

        return $this->done($request, 'Employee deleted.', route('employees.index'));
    }

    /** New rate closes the previous one the day before, preserving history. */
    public function storePayRate(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'pay_type' => ['required', Rule::in(array_keys(config('autoshop.enums.pay_types')))],
            'rate' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        abort_unless($request->user()->can('employees.edit'), 403);

        DB::transaction(function () use ($employee, $data, $request) {
            $from = Carbon::parse($data['effective_from']);
            if ($employee->payRates()->whereDate('effective_from', '>=', $from)->exists()) {
                abort(422, 'A rate already starts on or after this date.');
            }
            EmployeePayRate::where('employee_id', $employee->id)->whereNull('effective_to')
                ->update(['effective_to' => $from->copy()->subDay()->toDateString()]);
            $employee->payRates()->create($data + ['created_by' => $request->user()->id]);
        });

        return back()->with('success', 'Pay rate added. Previous rates are preserved for historical payroll.');
    }

    protected function formData(Employee $employee): array
    {
        return [
            'employee' => $employee,
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'managers' => Employee::where('id', '!=', $employee->id ?? 0)->where('employment_status', '!=', 'terminated')->orderBy('first_name')->get()->pluck('full_name', 'id'),
            'users' => User::where('status', 'approved')->whereDoesntHave('employee', fn ($q) => $q->where('id', '!=', $employee->id ?? 0))->orderBy('name')->pluck('name', 'id'),
        ];
    }

    protected function validated(Request $request, ?Employee $employee = null): array
    {
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $rules = [
            'employee_code' => ['nullable', 'string', 'max:30', Rule::unique('employees', 'employee_code')->ignore($employee?->id)],
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employee?->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'employment_status' => ['required', Rule::in(array_keys(config('autoshop.enums.employment_statuses')))],
            'employment_type' => ['required', 'in:full_time,part_time,contract,temporary'],
            'manager_id' => ['nullable', 'exists:employees,id', Rule::notIn([$employee?->id])],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photo' => Uploads::imageRule(),
            'work_schedule' => ['nullable', 'array'],
        ];
        foreach ($days as $d) {
            $rules["work_schedule.$d.start"] = ['nullable', 'date_format:H:i'];
            $rules["work_schedule.$d.end"] = ['nullable', 'date_format:H:i'];
        }
        if (! $employee) {
            $rules += ['pay_type' => ['nullable', Rule::in(array_keys(config('autoshop.enums.pay_types')))],
                'rate' => ['nullable', 'required_with:pay_type', 'numeric', 'min:0'], 'overtime_multiplier' => ['nullable', 'numeric', 'min:1', 'max:5']];
        }
        $data = $request->validate($rules);
        if (isset($data['work_schedule'])) {
            $data['work_schedule'] = collect($data['work_schedule'])->filter(fn ($d) => ! empty($d['start']) && ! empty($d['end']))->all();
        }

        return $data;
    }

    public function bulk(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'], 'action' => ['required', 'in:department,status'],
            'value' => ['required', 'string', 'max:30']]);
        abort_unless($request->user()->can('employees.edit'), 403);
        if ($data['action'] === 'status') {
            abort_unless(array_key_exists($data['value'], config('autoshop.enums.employment_statuses')), 422);
        }
        $n = Employee::whereIn('id', $data['ids'])->get()->each(fn ($e) => $e->update($data['action'] === 'department'
            ? ['department_id' => Department::find($data['value'])?->id] : ['employment_status' => $data['value']]))->count();

        return $this->done($request, "{$n} employee(s) updated.");
    }
}
