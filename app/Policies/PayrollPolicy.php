<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;

class PayrollPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('payrolls.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Payroll $payroll): bool
    {
        return $user->can('payrolls.view') && $user->canAccessCompany($payroll->company);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('payrolls.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Payroll $payroll): bool
    {
        return $payroll->isDraft()
            && $user->can('payrolls.edit')
            && $user->canAccessCompany($payroll->company);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payroll $payroll): bool
    {
        return $payroll->isDraft()
            && $user->can('payrolls.delete')
            && $user->canAccessCompany($payroll->company);
    }

    public function finalize(User $user, Payroll $payroll): bool
    {
        return $payroll->isDraft()
            && $user->can('payrolls.finalize')
            && $user->canAccessCompany($payroll->company);
    }

    public function reopen(User $user, Payroll $payroll): bool
    {
        return ! $payroll->isDraft()
            && $user->can('payrolls.reopen')
            && $user->canAccessCompany($payroll->company);
    }

    public function viewPayslip(User $user, Payroll $payroll): bool
    {
        return $user->can('payslips.view') && $user->canAccessCompany($payroll->company);
    }

    public function printPayslip(User $user, Payroll $payroll): bool
    {
        return $user->can('payslips.print') && $user->canAccessCompany($payroll->company);
    }

    public function export(User $user, Payroll $payroll): bool
    {
        return $user->can('payrolls.export') && $user->canAccessCompany($payroll->company);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Payroll $payroll): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Payroll $payroll): bool
    {
        return false;
    }
}
