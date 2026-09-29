<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase-orders.view');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.view') && $user->canAccessCompany($purchaseOrder->company);
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-orders.create');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.edit') && $user->canAccessCompany($purchaseOrder->company);
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.delete') && $user->canAccessCompany($purchaseOrder->company);
    }

    public function restore(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }

    public function forceDelete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }

    public function submit(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.submit') && $user->canAccessCompany($purchaseOrder->company);
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.approve') && $user->canAccessCompany($purchaseOrder->company);
    }

    public function reject(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.reject') && $user->canAccessCompany($purchaseOrder->company);
    }

    public function print(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-orders.print') && $user->canAccessCompany($purchaseOrder->company);
    }
}
