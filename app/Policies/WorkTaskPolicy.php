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
        return $user->can('view', $workTask->board) && $this->canParticipateInTask($user, $workTask);
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
        return $user->can('work-tasks.edit')
            && $user->can('view', $workTask->board)
            && $this->canManageTask($user, $workTask);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WorkTask $workTask): bool
    {
        return $user->can('work-tasks.delete')
            && $user->can('view', $workTask->board)
            && $this->canManageTask($user, $workTask);
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
        return $user->can('work-tasks.move')
            && $user->can('view', $workTask->board)
            && $this->canParticipateInTask($user, $workTask);
    }

    public function submitEod(User $user, WorkTask $workTask): bool
    {
        return $user->can('eod-reports.submit-own')
            && ($workTask->assigned_to === $user->id || $workTask->collaborators()->whereKey($user->id)->exists())
            && $user->can('view', $workTask->board);
    }

    private function canManageTask(User $user, WorkTask $workTask): bool
    {
        return $user->hasRole('Super Admin')
            || $workTask->created_by === $user->id
            || $workTask->assigned_to === $user->id;
    }

    private function canParticipateInTask(User $user, WorkTask $workTask): bool
    {
        return $this->canManageTask($user, $workTask)
            || $workTask->collaborators()->whereKey($user->id)->exists();
    }
}
