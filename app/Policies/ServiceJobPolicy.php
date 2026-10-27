<?php

namespace App\Policies;

use App\Models\ServiceJob;
use App\Models\User;

/** Record-level access: without service_jobs.view_all users only see jobs they are assigned to / supervise. */
class ServiceJobPolicy
{
    protected function assigned(User $user, ServiceJob $job): bool
    {
        $employeeId = $user->employee?->id;

        return $employeeId && ($job->supervisor_id === $employeeId || $job->employees()->where('employees.id', $employeeId)->exists());
    }

    public function viewAny(User $user): bool
    {
        return $user->can('service_jobs.view');
    }

    public function view(User $user, ServiceJob $job): bool
    {
        return $user->can('service_jobs.view') && ($user->can('service_jobs.view_all') || $this->assigned($user, $job));
    }

    public function create(User $user): bool
    {
        return $user->can('service_jobs.create');
    }

    public function update(User $user, ServiceJob $job): bool
    {
        return $user->can('service_jobs.edit') && ($user->can('service_jobs.view_all') || $this->assigned($user, $job));
    }

    public function approve(User $user, ServiceJob $job): bool
    {
        return $user->can('service_jobs.approve');
    }

    public function delete(User $user, ServiceJob $job): bool
    {
        return $user->can('service_jobs.delete');
    }
}
