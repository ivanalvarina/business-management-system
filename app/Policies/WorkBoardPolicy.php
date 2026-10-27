<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkBoard;

class WorkBoardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('work-boards.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WorkBoard $workBoard): bool
    {
        if (! $user->can('work-boards.view') || ! $user->canAccessCompany($workBoard->company)) {
            return false;
        }

        return $user->can('work-boards.view-all')
            || $workBoard->created_by === $user->id
            || $workBoard->members()->whereKey($user->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('work-boards.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WorkBoard $workBoard): bool
    {
        return $user->can('work-boards.edit') && $user->canAccessCompany($workBoard->company);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WorkBoard $workBoard): bool
    {
        return $user->can('work-boards.delete') && $user->canAccessCompany($workBoard->company);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, WorkBoard $workBoard): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, WorkBoard $workBoard): bool
    {
        return false;
    }
}
