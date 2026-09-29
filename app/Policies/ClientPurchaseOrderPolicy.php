<?php

namespace App\Policies;

use App\Models\ClientPurchaseOrder;
use App\Models\User;

class ClientPurchaseOrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('client-pos.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ClientPurchaseOrder $clientPurchaseOrder): bool
    {
        return $user->can('client-pos.view') && $user->canAccessCompany($clientPurchaseOrder->company);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('client-pos.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ClientPurchaseOrder $clientPurchaseOrder): bool
    {
        return $user->can('client-pos.edit')
            && $user->canAccessCompany($clientPurchaseOrder->company)
            && ! $clientPurchaseOrder->isFulfilled();
    }

    public function fulfill(User $user, ClientPurchaseOrder $clientPurchaseOrder): bool
    {
        return $user->can('client-pos.fulfill') && $user->canAccessCompany($clientPurchaseOrder->company);
    }

    public function cancel(User $user, ClientPurchaseOrder $clientPurchaseOrder): bool
    {
        return $user->can('client-pos.cancel') && $user->canAccessCompany($clientPurchaseOrder->company);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ClientPurchaseOrder $clientPurchaseOrder): bool
    {
        return $user->can('client-pos.delete') && $user->canAccessCompany($clientPurchaseOrder->company);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ClientPurchaseOrder $clientPurchaseOrder): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ClientPurchaseOrder $clientPurchaseOrder): bool
    {
        return false;
    }
}
