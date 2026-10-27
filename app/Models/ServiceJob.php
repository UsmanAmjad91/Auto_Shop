<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceJob extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['job_number', 'category', 'vehicle_id', 'customer_id', 'title', 'description', 'supervisor_id',
        'status', 'priority', 'scheduled_date', 'start_date', 'completion_date', 'odometer_in', 'is_billable',
        'checklist', 'notes', 'customer_charge', 'created_by'];

    protected function casts(): array
    {
        return ['scheduled_date' => 'date', 'start_date' => 'datetime', 'completion_date' => 'datetime',
            'checklist' => 'array', 'is_billable' => 'boolean',
            'labor_cost' => 'decimal:2', 'material_cost' => 'decimal:2', 'parts_cost' => 'decimal:2',
            'total_cost' => 'decimal:2', 'customer_charge' => 'decimal:2'];
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function items()
    {
        return $this->hasMany(ServiceJobItem::class);
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'service_job_employees')->withPivot('role', 'hours_worked')->withTimestamps();
    }

    public function images()
    {
        return $this->hasMany(ServiceJobImage::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tasks()
    {
        return $this->hasMany(EmployeeTask::class);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['delivered', 'cancelled'], true);
    }

    public function categoryLabel(): string
    {
        return config('autoshop.enums.service_categories.'.$this->category, ucfirst($this->category));
    }

    /** Record-level visibility: users without view_all only see jobs they are assigned to. */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->can('service_jobs.view_all')) {
            return $query;
        }
        $employeeId = $user->employee?->id ?? 0;

        return $query->where(fn ($q) => $q->where('supervisor_id', $employeeId)
            ->orWhereHas('employees', fn ($e) => $e->where('employees.id', $employeeId)));
    }
}
