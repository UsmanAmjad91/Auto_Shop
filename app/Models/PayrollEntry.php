<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PayrollEntry extends Model
{
    use Auditable;

    protected $fillable = ['payroll_period_id', 'employee_id', 'pay_rate_id', 'pay_type', 'rate', 'overtime_multiplier',
        'regular_hours', 'overtime_hours', 'days_worked', 'base_pay', 'overtime_pay', 'bonuses', 'gross_earnings',
        'deductions', 'advances', 'net_pay', 'amount_paid', 'remaining_payable', 'payment_status',
        'deduction_breakdown', 'notes'];

    protected function casts(): array
    {
        return ['deduction_breakdown' => 'array'];
    }

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function payments()
    {
        return $this->hasMany(EmployeePayment::class);
    }
}
