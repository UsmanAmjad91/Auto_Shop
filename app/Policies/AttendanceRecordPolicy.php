<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    public function view(User $user, AttendanceRecord $record): bool
    {
        return $user->can('attendance.view_all') || ($user->can('attendance.view') && $user->employee?->id === $record->employee_id);
    }

    public function update(User $user, AttendanceRecord $record): bool
    {
        return $user->can('attendance.edit');
    }

    public function delete(User $user, AttendanceRecord $record): bool
    {
        return $user->can('attendance.delete');
    }
}
