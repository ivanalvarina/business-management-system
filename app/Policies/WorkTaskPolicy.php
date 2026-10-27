<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkTask;

class WorkTaskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WorkTask $workTask): bool
    {
        return $user->can('view', $workTask->board);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('work-tasks.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WorkTask $workTask): bool
    {
        return $user->can('work-tasks.edit') && $user->can('view', $workTask->board);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WorkTask $workTask): bool
    {
        return $user->can('work-tasks.delete') && $user->can('view', $workTask->board);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, WorkTask $workTask): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, WorkTask $workTask): bool
    {
        return false;
    }

    public function move(User $user, WorkTask $workTask): bool
    {
        return $user->can('work-tasks.move') && $user->can('view', $workTask->board);
    }

    public function submitEod(User $user, WorkTask $workTask): bool
    {
        return $user->can('eod-reports.submit-own')
            && $workTask->assigned_to === $user->id
            && $user->can('view', $workTask->board);
    }
}
