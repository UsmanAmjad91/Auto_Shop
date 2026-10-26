<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** Only a Super Administrator may manage another Super Administrator. */
    public function update(User $actor, User $target): bool
    {
        if (! $actor->can('users.edit')) {
            return false;
        }

        return ! $target->hasRole('Super Administrator') || $actor->hasRole('Super Administrator');
    }

    public function approve(User $actor, User $target): bool
    {
        return $actor->can('users.approve') && $actor->id !== $target->id
            && (! $target->hasRole('Super Administrator') || $actor->hasRole('Super Administrator'));
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('users.delete') && $actor->id !== $target->id && ! $target->hasRole('Super Administrator');
    }
}
