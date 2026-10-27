<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class EmployeePayment extends Model
{
    use Auditable;

    protected $fillable = ['employee_id', 'payroll_entry_id', 'type', 'payment_date', 'amount', 'method', 'reference', 'notes', 'paid_by'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function entry()
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
