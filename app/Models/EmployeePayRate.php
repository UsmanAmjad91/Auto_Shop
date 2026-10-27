<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class EmployeePayRate extends Model
{
    use Auditable;

    protected $fillable = ['employee_id', 'pay_type', 'rate', 'overtime_multiplier', 'effective_from', 'effective_to', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'rate' => 'decimal:2', 'overtime_multiplier' => 'decimal:2'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
