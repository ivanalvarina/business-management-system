<?php

namespace App\Policies;

use App\Models\ReceivingReceipt;
use App\Models\User;

class ReceivingReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('receiving-receipts.view');
    }

    public function view(User $user, ReceivingReceipt $receivingReceipt): bool
    {
        return $user->can('receiving-receipts.view') && $user->canAccessCompany($receivingReceipt->company);
    }

    public function create(User $user): bool
    {
        return $user->can('receiving-receipts.create');
    }

    public function update(User $user, ReceivingReceipt $receivingReceipt): bool
    {
        return $user->can('receiving-receipts.edit') && $user->canAccessCompany($receivingReceipt->company);
    }

    public function delete(User $user, ReceivingReceipt $receivingReceipt): bool
    {
        return $user->can('receiving-receipts.delete') && $user->canAccessCompany($receivingReceipt->company);
    }

    public function restore(User $user, ReceivingReceipt $receivingReceipt): bool
    {
        return false;
    }

    public function forceDelete(User $user, ReceivingReceipt $receivingReceipt): bool
    {
        return false;
    }

    public function print(User $user, ReceivingReceipt $receivingReceipt): bool
    {
        return $user->can('receiving-receipts.print') && $user->canAccessCompany($receivingReceipt->company);
    }
}
