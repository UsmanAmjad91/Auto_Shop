<?php

namespace App\Http\Controllers;

use App\Models\EmployeePayment;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Services\InvoicePdf;
use App\Services\PayrollService;
use App\Support\DataTable;
use App\Support\Exporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PayrollController extends Controller
{
    public function __construct(protected PayrollService $payroll) {}

    public function index(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('payroll.index');
        }

        return DataTable::make(PayrollPeriod::withCount('entries'), $request)
            ->searchable(['name'])
            ->sortable(['start_date' => 'start_date', 'end_date' => 'end_date', 'total_net' => 'total_net'], 'start_date')
            ->filters(fn ($q, $r) => $q->when($r->status, fn ($x) => $x->where('status', $r->status))
                ->when($r->date_from, fn ($x) => $x->whereDate('end_date', '>=', $r->date_from))
                ->when($r->date_to, fn ($x) => $x->whereDate('start_date', '<=', $r->date_to)))
            ->transform(fn (PayrollPeriod $p) => [
                'id' => $p->id, 'url' => route('payroll.show', $p), 'name' => $p->name, 'frequency' => ucfirst($p->frequency),
                'period' => fmt_date($p->start_date).' → '.fmt_date($p->end_date), 'pay_date' => fmt_date($p->pay_date), 'employees' => $p->entries_count,
                'gross' => (float) $p->total_gross, 'net' => (float) $p->total_net, 'paid' => (float) $p->total_paid,
                'remaining' => round((float) $p->total_net - (float) $p->total_paid, 2),
                'status' => ['label' => status_label($p->status), 'color' => status_color($p->status)],
                'actions' => [['label' => 'Open', 'icon' => 'eye', 'url' => route('payroll.show', $p)]],
            ])->response();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'frequency' => ['required', 'in:weekly,biweekly,monthly,custom'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pay_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['name'] = $data['name'] ?: ucfirst($data['frequency']).' payroll '.Carbon::parse($data['start_date'])->format('M j').' – '.Carbon::parse($data['end_date'])->format('M j, Y');
        $period = PayrollPeriod::create($data + ['status' => 'draft', 'created_by' => $request->user()->id]);
        $count = $this->payroll->generate($period);

        return redirect()->route('payroll.show', $period)->with('success', "Payroll period created with {$count} employee entries.");
    }

    public function show(PayrollPeriod $period)
    {
        return view('payroll.show', ['period' => $period->load(['entries.employee.department', 'entries.payments', 'approver'])]);
    }

    public function generate(PayrollPeriod $period)
    {
        $n = $this->payroll->generate($period);

        return back()->with('success', "Recalculated {$n} entries from attendance and current rules (manual bonuses/deductions kept).");
    }

    public function approve(PayrollPeriod $period)
    {
        $this->payroll->approve($period);

        return back()->with('success', 'Payroll approved. Entries are now locked and can be paid.');
    }

    public function destroy(Request $request, PayrollPeriod $period)
    {
        abort_unless($period->status === 'draft', 422, 'Only draft payroll periods can be deleted.');
        $period->entries()->delete();
        $period->delete();

        return $this->done($request, 'Payroll period deleted.', route('payroll.index'));
    }

    public function updateEntry(Request $request, PayrollEntry $entry)
    {
        $data = $request->validate([
            'base_pay' => ['required', 'numeric', 'min:0'], 'overtime_pay' => ['required', 'numeric', 'min:0'],
            'bonuses' => ['required', 'numeric', 'min:0'], 'deductions' => ['required', 'numeric', 'min:0'],
            'advances' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->payroll->updateAdjustments($entry, $data);

        return $this->done($request, 'Entry updated.');
    }

    public function pay(Request $request, PayrollEntry $entry)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'], 'payment_date' => ['required', 'date'],
            'method' => ['required', 'in:cash,bank_transfer,check,other'], 'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $this->payroll->pay($entry, $data);

        return $this->done($request, 'Payment recorded.');
    }

    public function payslip(Request $request, PayrollEntry $entry)
    {
        $this->authorize('view', $entry);
        $entry->load(['employee.department', 'period', 'payments']);
        $pdf = Pdf::loadView('payroll.payslip', ['entry' => $entry, 'logo' => InvoicePdf::logoDataUri()]);

        return $request->boolean('inline') ? $pdf->stream("payslip-{$entry->id}.pdf") : $pdf->download("payslip-{$entry->employee->employee_code}-{$entry->period->end_date->format('Ymd')}.pdf");
    }

    public function mine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'Your account is not linked to an employee profile.');

        return view('payroll.mine', ['entries' => $employee->payrollEntries()->with('period')
            ->whereHas('period', fn ($q) => $q->where('status', '!=', 'draft'))->latest('id')->get()]);
    }

    public function payments(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('payroll.payments');
        }

        return DataTable::make(EmployeePayment::with(['employee', 'entry.period', 'payer']), $request)
            ->searchable(['reference', 'employee.first_name', 'employee.last_name'])
            ->sortable(['payment_date' => 'payment_date', 'amount' => 'amount'], 'payment_date')
            ->filters(fn ($q, $r) => $q->when($r->employee_id, fn ($x) => $x->where('employee_id', $r->employee_id))
                ->when($r->method, fn ($x) => $x->where('method', $r->method))
                ->when($r->date_from, fn ($x) => $x->whereDate('payment_date', '>=', $r->date_from))
                ->when($r->date_to, fn ($x) => $x->whereDate('payment_date', '<=', $r->date_to)))
            ->transform(fn (EmployeePayment $p) => [
                'id' => $p->id, 'date' => fmt_date($p->payment_date), 'employee' => $p->employee?->full_name, 'period' => $p->entry?->period?->name,
                'amount' => (float) $p->amount, 'method' => status_label($p->method), 'reference' => $p->reference, 'by' => $p->payer?->name,
                'url' => $p->entry ? route('payroll.show', $p->entry->payroll_period_id) : null,
            ])->response();
    }

    public function export(Request $request, PayrollPeriod $period)
    {
        $rows = $period->entries()->with('employee')->get()->map(fn ($e) => [
            'code' => $e->employee->employee_code, 'employee' => $e->employee->full_name, 'pay_type' => $e->pay_type, 'rate' => $e->rate,
            'regular' => $e->regular_hours, 'ot' => $e->overtime_hours, 'days' => $e->days_worked, 'base' => $e->base_pay, 'ot_pay' => $e->overtime_pay,
            'bonuses' => $e->bonuses, 'gross' => $e->gross_earnings, 'deductions' => $e->deductions, 'advances' => $e->advances, 'net' => $e->net_pay,
            'paid' => $e->amount_paid, 'remaining' => $e->remaining_payable, 'status' => $e->payment_status]);

        return Exporter::download($request->input('format', 'csv'), 'Payroll '.$period->name, ['code' => 'Emp ID', 'employee' => 'Employee', 'pay_type' => 'Pay Type',
            'rate' => 'Rate', 'regular' => 'Reg Hrs', 'ot' => 'OT Hrs', 'days' => 'Days', 'base' => 'Base', 'ot_pay' => 'OT Pay', 'bonuses' => 'Bonuses',
            'gross' => 'Gross', 'deductions' => 'Deductions', 'advances' => 'Advances', 'net' => 'Net', 'paid' => 'Paid', 'remaining' => 'Remaining', 'status' => 'Status'], $rows);
    }
}
