<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class EmployeeTask extends Model
{
    use Auditable;

    public const STATUSES = ['open' => 'Open', 'in_progress' => 'In Progress', 'done' => 'Done', 'cancelled' => 'Cancelled'];

    protected $fillable = ['title', 'description', 'employee_id', 'vehicle_id', 'service_job_id', 'due_date', 'priority',
        'status', 'assigned_by', 'completed_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function serviceJob()
    {
        return $this->belongsTo(ServiceJob::class);
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
