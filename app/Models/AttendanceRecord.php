<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use Auditable;

    protected $fillable = ['employee_id', 'work_date', 'clock_in', 'clock_out', 'break_start', 'break_end', 'break_minutes',
        'total_hours', 'regular_hours', 'overtime_hours', 'status', 'source', 'notes', 'corrected_by', 'corrected_at'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'clock_in' => 'datetime', 'clock_out' => 'datetime', 'break_start' => 'datetime',
            'break_end' => 'datetime', 'corrected_at' => 'datetime',
            'total_hours' => 'decimal:2', 'regular_hours' => 'decimal:2', 'overtime_hours' => 'decimal:2'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function corrector()
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
