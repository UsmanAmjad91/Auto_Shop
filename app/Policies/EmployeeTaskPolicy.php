<?php

namespace App\Policies;

use App\Models\EmployeeTask;
use App\Models\User;

class EmployeeTaskPolicy
{
    protected function mine(User $user, EmployeeTask $task): bool
    {
        return $user->employee && $task->employee_id === $user->employee->id;
    }

    public function view(User $user, EmployeeTask $task): bool
    {
        return $user->can('tasks.view_all') || ($user->can('tasks.view') && $this->mine($user, $task));
    }

    /** Assignees may update status of their own tasks. */
    public function update(User $user, EmployeeTask $task): bool
    {
        return $user->can('tasks.edit') && ($user->can('tasks.view_all') || $this->mine($user, $task));
    }

    public function delete(User $user, EmployeeTask $task): bool
    {
        return $user->can('tasks.delete');
    }
}
