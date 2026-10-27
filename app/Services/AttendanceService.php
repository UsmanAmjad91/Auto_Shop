<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function today(Employee $employee): ?AttendanceRecord
    {
        return AttendanceRecord::where('employee_id', $employee->id)->whereDate('work_date', today())->first();
    }

    /** Self-service punch actions: clock_in, break_start, break_end, clock_out. */
    public function punch(Employee $employee, string $action): AttendanceRecord
    {
        $record = $this->today($employee);
        $now = now();

        switch ($action) {
            case 'clock_in':
                if ($record?->clock_in) {
                    throw ValidationException::withMessages(['action' => 'Already clocked in today.']);
                }
                $record ??= new AttendanceRecord(['employee_id' => $employee->id, 'work_date' => today()]);
                $record->clock_in = $now;
                $record->source = 'self_service';
                $record->status = $this->isLate($employee, $now) ? 'late' : 'present';
                break;
            case 'break_start':
                $this->requireOpen($record);
                if ($record->break_start && ! $record->break_end) {
                    throw ValidationException::withMessages(['action' => 'Break already started.']);
                }
                $record->break_start = $now;
                $record->break_end = null;
                break;
            case 'break_end':
                $this->requireOpen($record);
                if (! $record->break_start || $record->break_end) {
                    throw ValidationException::withMessages(['action' => 'No break in progress.']);
                }
                $record->break_end = $now;
                $record->break_minutes += (int) $record->break_start->diffInMinutes($now);
                break;
            case 'clock_out':
                $this->requireOpen($record);
                if ($record->break_start && ! $record->break_end) {
                    $record->break_end = $now;
                    $record->break_minutes += (int) $record->break_start->diffInMinutes($now);
                }
                $record->clock_out = $now;
                break;
            default:
                throw ValidationException::withMessages(['action' => 'Unknown action.']);
        }

        $this->computeHours($record);
        $record->save();

        return $record;
    }

    protected function requireOpen(?AttendanceRecord $record): void
    {
        if (! $record?->clock_in) {
            throw ValidationException::withMessages(['action' => 'Clock in first.']);
        }
        if ($record->clock_out) {
            throw ValidationException::withMessages(['action' => 'Already clocked out today.']);
        }
    }

    protected function isLate(Employee $employee, Carbon $time): bool
    {
        $day = strtolower($time->format('D'));
        $start = $employee->work_schedule[$day]['start'] ?? settings('payroll.work_start', '08:00');
        $grace = (int) settings('payroll.late_grace_minutes', 10);

        return $time->greaterThan(Carbon::parse($time->toDateString().' '.$start)->addMinutes($grace));
    }

    /** Total = (out - in) - breaks; overtime beyond the configured daily threshold. */
    public function computeHours(AttendanceRecord $record): AttendanceRecord
    {
        if ($record->clock_in && $record->clock_out) {
            if ($record->clock_out->lessThan($record->clock_in)) {
                throw ValidationException::withMessages(['clock_out' => 'Clock out must be after clock in.']);
            }
            if ($record->break_start && $record->break_end && ! $record->isDirty('break_minutes')) {
                $record->break_minutes = max((int) $record->break_minutes, (int) $record->break_start->diffInMinutes($record->break_end));
            }
            $minutes = max(0, (int) $record->clock_in->diffInMinutes($record->clock_out) - (int) $record->break_minutes);
            $total = round($minutes / 60, 2);
            $threshold = (float) settings('payroll.daily_overtime_threshold', 8);
            $record->total_hours = $total;
            $record->regular_hours = min($total, $threshold);
            $record->overtime_hours = max(0, round($total - $threshold, 2));
        } else {
            $record->total_hours = $record->regular_hours = $record->overtime_hours = 0;
        }

        return $record;
    }

    /** Manager correction (audited through the Auditable trait + corrected_by). */
    public function saveManual(array $data, ?AttendanceRecord $record = null): AttendanceRecord
    {
        $record ??= AttendanceRecord::firstOrNew(['employee_id' => $data['employee_id'], 'work_date' => $data['work_date']]);
        $date = Carbon::parse($data['work_date'])->toDateString();
        $at = fn ($t) => $t ? Carbon::parse(strlen($t) <= 5 ? "$date $t" : $t) : null;

        $record->fill([
            'employee_id' => $data['employee_id'],
            'work_date' => $date,
            'clock_in' => $at($data['clock_in'] ?? null),
            'clock_out' => $at($data['clock_out'] ?? null),
            'break_start' => $at($data['break_start'] ?? null),
            'break_end' => $at($data['break_end'] ?? null),
            'break_minutes' => (int) ($data['break_minutes'] ?? 0),
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);
        if ($record->clock_out && $record->clock_in && $record->clock_out->lt($record->clock_in)) {
            $record->clock_out = $record->clock_out->addDay(); // overnight shift
        }
        if ($record->exists) {
            $record->corrected_by = auth()->id();
            $record->corrected_at = now();
        } else {
            $record->source = 'manual';
        }
        $this->computeHours($record);
        $record->save();

        return $record;
    }
}
