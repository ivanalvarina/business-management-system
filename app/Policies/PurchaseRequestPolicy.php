<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase-requests.view');
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.view') && $user->canAccessCompany($purchaseRequest->company);
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-requests.create');
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.edit') && $user->canAccessCompany($purchaseRequest->company);
    }

    public function delete(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.delete') && $user->canAccessCompany($purchaseRequest->company);
    }

    public function restore(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return false;
    }

    public function forceDelete(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return false;
    }

    public function submit(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.submit') && $user->canAccessCompany($purchaseRequest->company);
    }

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.approve') && $user->canAccessCompany($purchaseRequest->company);
    }

    public function reject(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.reject') && $user->canAccessCompany($purchaseRequest->company);
    }

    public function print(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('purchase-requests.print') && $user->canAccessCompany($purchaseRequest->company);
    }
}
