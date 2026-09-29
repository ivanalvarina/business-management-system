<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('quotations.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.view') && $user->canAccessCompany($quotation->company);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('quotations.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.edit') && $user->canAccessCompany($quotation->company);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.delete') && $user->canAccessCompany($quotation->company);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Quotation $quotation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Quotation $quotation): bool
    {
        return false;
    }

    public function submit(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.submit') && $user->canAccessCompany($quotation->company);
    }

    public function approve(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.approve') && $user->canAccessCompany($quotation->company);
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.reject') && $user->canAccessCompany($quotation->company);
    }

    public function print(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.print') && $user->canAccessCompany($quotation->company);
    }
}
