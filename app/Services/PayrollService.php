<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payroll calculation helper.
 *
 * IMPORTANT: this produces an *estimate* of gross/net pay from attendance and
 * configured rules (overtime thresholds, multiplier). It does not compute
 * statutory tax withholding or jurisdiction-specific labour rules; enter those
 * as deductions or use dedicated payroll software / an accountant for compliance.
 */
class PayrollService
{
    public function generate(PayrollPeriod $period, ?array $employeeIds = null): int
    {
        if (! $period->isEditable()) {
            throw ValidationException::withMessages(['period' => 'Only draft payroll periods can be (re)generated.']);
        }

        return DB::transaction(function () use ($period, $employeeIds) {
            $employees = Employee::query()
                ->where(fn ($q) => $q->where('employment_status', '!=', 'terminated')
                    ->orWhereDate('termination_date', '>=', $period->start_date))
                ->when($employeeIds, fn ($q) => $q->whereIn('id', $employeeIds))
                ->get();

            $count = 0;
            foreach ($employees as $employee) {
                if ($this->buildEntry($period, $employee)) {
                    $count++;
                }
            }
            $this->refreshPeriod($period);

            return $count;
        });
    }

    public function buildEntry(PayrollPeriod $period, Employee $employee): ?PayrollEntry
    {
        $rate = $employee->payRateOn($period->start_date) ?? $employee->payRateOn($period->end_date);
        if (! $rate) {
            return null; // no pay rate configured for this period
        }

        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDateBetween('work_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
            ->get();

        $worked = $records->whereIn('status', ['present', 'late']);
        $paidLeave = $records->whereIn('status', ['holiday']);
        [$regular, $overtime] = $this->splitHours($worked);
        $days = $worked->count();

        $periodDays = $period->start_date->diffInDays($period->end_date) + 1;
        $base = match ($rate->pay_type) {
            'hourly' => Money::mul($rate->rate, $regular),
            'daily' => Money::mul($rate->rate, $days + $paidLeave->count()),
            'weekly' => Money::mul($rate->rate, round($periodDays / 7, 4)),
            'biweekly' => Money::mul($rate->rate, round($periodDays / 14, 4)),
            'monthly' => Money::mul($rate->rate, $this->monthFraction($period->start_date, $period->end_date)),
            'fixed' => (float) $rate->rate,
            default => 0.0,
        };

        // Overtime premium only for hourly / daily paid staff by default.
        $hourlyEquivalent = match ($rate->pay_type) {
            'hourly' => (float) $rate->rate,
            'daily' => (float) $rate->rate / max(1, (float) settings('payroll.daily_overtime_threshold', 8)),
            default => 0.0,
        };
        $overtimePay = Money::mul($hourlyEquivalent * (float) $rate->overtime_multiplier, $overtime);

        $entry = PayrollEntry::firstOrNew(['payroll_period_id' => $period->id, 'employee_id' => $employee->id]);
        $entry->fill([
            'pay_rate_id' => $rate->id,
            'pay_type' => $rate->pay_type,
            'rate' => $rate->rate,
            'overtime_multiplier' => $rate->overtime_multiplier,
            'regular_hours' => $regular,
            'overtime_hours' => $overtime,
            'days_worked' => $days,
            'base_pay' => $base,
            'overtime_pay' => $overtimePay,
            'bonuses' => $entry->bonuses ?? 0,
            'deductions' => $entry->deductions ?? 0,
            'advances' => $entry->advances ?? 0,
        ]);
        $this->computeEntry($entry);
        $entry->save();

        return $entry;
    }

    /** Daily overtime from attendance, plus weekly overtime beyond the weekly threshold. */
    protected function splitHours($records): array
    {
        $weeklyThreshold = (float) settings('payroll.weekly_overtime_threshold', 40);
        $regular = 0.0;
        $overtime = 0.0;

        foreach ($records->groupBy(fn ($r) => $r->work_date->format('o-W')) as $week) {
            $weekRegular = (float) $week->sum('regular_hours');
            $weekOvertime = (float) $week->sum('overtime_hours');
            if ($weeklyThreshold > 0 && $weekRegular > $weeklyThreshold) {
                $weekOvertime += $weekRegular - $weeklyThreshold;
                $weekRegular = $weeklyThreshold;
            }
            $regular += $weekRegular;
            $overtime += $weekOvertime;
        }

        return [round($regular, 2), round($overtime, 2)];
    }

    protected function monthFraction(Carbon $start, Carbon $end): float
    {
        $fraction = 0.0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $monthEnd = $cursor->copy()->endOfMonth();
            $segmentEnd = $monthEnd->lt($end) ? $monthEnd : $end;
            $fraction += ($cursor->diffInDays($segmentEnd) + 1) / $cursor->daysInMonth;
            $cursor = $segmentEnd->copy()->addDay()->startOfDay();
        }

        return $fraction;
    }

    public function computeEntry(PayrollEntry $entry): PayrollEntry
    {
        $gross = Money::add($entry->base_pay, $entry->overtime_pay, $entry->bonuses);
        $net = Money::sub(Money::sub($gross, $entry->deductions), $entry->advances);
        $paid = $entry->exists ? Money::sum($entry->payments()->pluck('amount')) : (float) $entry->amount_paid;
        $remaining = Money::sub($net, $paid);

        $entry->gross_earnings = $gross;
        $entry->net_pay = $net;
        $entry->amount_paid = $paid;
        $entry->remaining_payable = max(0, $remaining);
        $entry->payment_status = $this->statusFor($entry);

        return $entry;
    }

    protected function statusFor(PayrollEntry $entry): string
    {
        if ((float) $entry->net_pay <= 0 || (float) $entry->remaining_payable <= 0) {
            return (float) $entry->amount_paid > 0 || (float) $entry->net_pay <= 0 ? 'paid' : 'unpaid';
        }
        $period = $entry->period;
        $due = $period?->pay_date?->copy()->addDays((int) settings('payroll.payment_due_days', 3));
        if ($due && $due->isPast()) {
            return 'overdue';
        }

        return (float) $entry->amount_paid > 0 ? 'partially_paid' : 'unpaid';
    }

    public function updateAdjustments(PayrollEntry $entry, array $data): PayrollEntry
    {
        if (! $entry->period->isEditable()) {
            throw ValidationException::withMessages(['entry' => 'Approved payroll cannot be edited.']);
        }
        $entry->fill(collect($data)->only(['base_pay', 'overtime_pay', 'bonuses', 'deductions', 'advances', 'notes', 'deduction_breakdown'])->all());
        $this->computeEntry($entry)->save();
        $this->refreshPeriod($entry->period);

        return $entry;
    }

    public function approve(PayrollPeriod $period): void
    {
        if ($period->status !== 'draft') {
            throw ValidationException::withMessages(['period' => 'Period is not in draft.']);
        }
        if (! $period->entries()->exists()) {
            throw ValidationException::withMessages(['period' => 'Generate payroll entries first.']);
        }
        $period->forceFill(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()])->save();
    }

    public function pay(PayrollEntry $entry, array $data): EmployeePayment
    {
        if ($entry->period->status === 'draft') {
            throw ValidationException::withMessages(['entry' => 'Approve the payroll period before paying.']);
        }
        $amount = (float) $data['amount'];
        if ($amount <= 0 || Money::toCents($amount) > Money::toCents($entry->remaining_payable)) {
            throw ValidationException::withMessages(['amount' => 'Amount must be between 0 and '.money($entry->remaining_payable).'.']);
        }

        return DB::transaction(function () use ($entry, $data, $amount) {
            $payment = $entry->payments()->create([
                'employee_id' => $entry->employee_id,
                'type' => 'salary',
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_by' => auth()->id(),
            ]);
            $this->computeEntry($entry->fresh())->save();
            $this->refreshPeriod($entry->period);

            return $payment;
        });
    }

    public function refreshPeriod(PayrollPeriod $period): void
    {
        $entries = $period->entries()->get();
        $period->forceFill([
            'total_gross' => Money::sum($entries->pluck('gross_earnings')),
            'total_net' => Money::sum($entries->pluck('net_pay')),
            'total_paid' => Money::sum($entries->pluck('amount_paid')),
        ]);
        if ($period->status === 'approved' && $entries->isNotEmpty() && $entries->every(fn ($e) => (float) $e->remaining_payable <= 0)) {
            $period->status = 'closed';
        }
        $period->save();
    }

    /** Refresh "overdue" flags (scheduled daily). */
    public function markOverdue(): int
    {
        $n = 0;
        PayrollEntry::with('period')->whereIn('payment_status', ['unpaid', 'partially_paid'])->each(function ($e) use (&$n) {
            $before = $e->payment_status;
            $this->computeEntry($e)->save();
            $n += $before !== $e->payment_status ? 1 : 0;
        });

        return $n;
    }
}
