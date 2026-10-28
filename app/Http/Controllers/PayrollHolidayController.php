<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollHolidayRequest;
use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollHoliday;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PayrollHolidayController extends Controller
{
    public function store(StorePayrollHolidayRequest $request, Company $company, AuditLogger $audit): RedirectResponse
    {
        $holiday = $company->payrollHolidays()->create($request->validated());
        $audit->record('payroll_holidays', 'created', $holiday, newValues: $holiday->only(['company_id', 'date', 'name']), request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll holiday added.')]);

        return back();
    }

    public function destroy(Request $request, Company $company, PayrollHoliday $payrollHoliday, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('create', Payroll::class);
        abort_unless($request->user()->canAccessCompany($company), 403);
        abort_unless($payrollHoliday->company_id === $company->id, 404);

        $oldValues = $payrollHoliday->only(['company_id', 'date', 'name']);
        $payrollHoliday->delete();
        $audit->record('payroll_holidays', 'deleted', $payrollHoliday, oldValues: $oldValues, request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll holiday removed.')]);

        return back();
    }
}
