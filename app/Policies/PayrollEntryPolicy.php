<?php

namespace App\Policies;

use App\Models\PayrollEntry;
use App\Models\User;

class PayrollEntryPolicy
{
    /** Payroll staff, or the employee viewing their own payslip once approved. */
    public function view(User $user, PayrollEntry $entry): bool
    {
        if ($user->can('payroll.view')) {
            return true;
        }

        return $user->employee?->id === $entry->employee_id && $entry->period->status !== 'draft';
    }
}
