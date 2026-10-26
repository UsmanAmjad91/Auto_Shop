<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Employee extends Model
{
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    protected $fillable = ['employee_code', 'user_id', 'first_name', 'last_name', 'photo', 'email', 'phone', 'address',
        'job_title', 'department_id', 'hire_date', 'termination_date', 'employment_status', 'employment_type',
        'manager_id', 'work_schedule', 'emergency_contact_name', 'emergency_contact_phone',
        'emergency_contact_relation', 'notes'];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return ['hire_date' => 'date', 'termination_date' => 'date', 'work_schedule' => 'array'];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function payRates()
    {
        return $this->hasMany(EmployeePayRate::class)->orderByDesc('effective_from');
    }

    public function attendance()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function payrollEntries()
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function payments()
    {
        return $this->hasMany(EmployeePayment::class);
    }

    public function serviceJobs()
    {
        return $this->belongsToMany(ServiceJob::class, 'service_job_employees')->withPivot('role', 'hours_worked')->withTimestamps();
    }

    public function tasks()
    {
        return $this->hasMany(EmployeeTask::class);
    }

    /** The pay rate in effect on a given date (historical rates are preserved). */
    public function payRateOn(Carbon|string|null $date = null): ?EmployeePayRate
    {
        $date = Carbon::parse($date ?? now())->toDateString();

        return $this->payRates()
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->first();
    }

    public function currentPayRate(): ?EmployeePayRate
    {
        return $this->payRateOn(now());
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? asset('storage/'.$this->photo) : null;
    }

    public function scopeActive($q)
    {
        return $q->where('employment_status', 'active');
    }
}
