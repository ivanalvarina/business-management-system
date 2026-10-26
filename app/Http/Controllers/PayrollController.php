<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollRequest;
use App\Http\Requests\UpdatePayrollRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Support\PayrollCalculator;
use App\Support\PayrollPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payroll::class);
        $company = $this->selectedCompany($request, $request->user());

        return Inertia::render('payrolls/Index', [
            'companies' => $this->companyOptions($request->user()),
            'filters' => ['company_id' => $company?->id],
            'payrolls' => Payroll::query()
                ->select(['id', 'company_id', 'period_start', 'period_end', 'pay_date', 'status', 'finalized_at'])
                ->with('company:id,company_code,company_name')
                ->withCount('items')
                ->withSum('items', 'gross_pay')
                ->withSum('items', 'net_pay')
                ->when($company !== null, fn (Builder $query) => $query->whereBelongsTo($company))
                ->when($company === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->latest('period_end')
                ->paginate(15)
                ->withQueryString(),
            'can' => ['create' => $request->user()->can('create', Payroll::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Payroll::class);
        $companies = $this->companyOptions($request->user());

        return Inertia::render('payrolls/Create', [
            'companies' => $companies,
            'defaultCompanyId' => $this->selectedCompany($request, $request->user())?->id ?? $companies[0]['id'] ?? null,
            'defaultMonth' => now()->format('Y-m'),
        ]);
    }

    public function store(StorePayrollRequest $request, PayrollCalculator $calculator): RedirectResponse
    {
        $period = PayrollPeriod::forPayday($request->string('payroll_month')->toString(), $request->integer('payday'));
        $company = Company::findOrFail($request->integer('company_id'));

        if (Payroll::whereBelongsTo($company)->whereDate('period_start', $period['period_start'])->whereDate('period_end', $period['period_end'])->exists()) {
            throw ValidationException::withMessages(['payroll_month' => __('A payroll already exists for this cutoff.')]);
        }

        $payroll = DB::transaction(function () use ($company, $period, $request, $calculator): Payroll {
            $settings = PayrollSetting::firstOrCreate(['company_id' => $company->id], PayrollSetting::defaults());
            $payroll = Payroll::create([
                'company_id' => $company->id,
                'period_start' => $period['period_start'],
                'period_end' => $period['period_end'],
                'pay_date' => $period['pay_date'],
                'status' => Payroll::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);
            Employee::query()
                ->whereBelongsTo($company)
                ->active()
                ->where('pay_frequency', Employee::PAY_FREQUENCY_SEMI_MONTHLY)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->each(function (Employee $employee) use ($payroll, $settings, $calculator): void {
                    $values = [
                        'monthly_basic_salary' => $employee->basic_salary,
                        ...$settings->only(array_keys(PayrollSetting::defaults())),
                        'days_worked' => $settings->payroll_days_basis,
                        'absent_days' => 0,
                        'late_hours' => 0,
                        'regular_ot_hours' => 0,
                        'special_holiday_hours' => 0,
                        'special_holiday_ot_hours' => 0,
                        'regular_holiday_hours' => 0,
                        'regular_holiday_ot_hours' => 0,
                        'salary_adjustment' => 0,
                        'sss_contribution' => 0,
                        'philhealth_contribution' => 0,
                        'pagibig_contribution' => 0,
                        'withholding_tax' => 0,
                        'other_deduction' => 0,
                        'allowance' => 0,
                    ];

                    $payroll->items()->create([
                        'employee_id' => $employee->id,
                        'employee_number' => $employee->employee_number,
                        'employee_name' => $employee->fullName(),
                        'department' => $employee->department,
                        'job_title' => $employee->job_title,
                        'pay_frequency' => $employee->pay_frequency,
                        'bank_name' => $employee->bank_name,
                        'bank_account_last_four' => $employee->bank_account_number ? substr($employee->bank_account_number, -4) : null,
                        ...$values,
                        ...$calculator->calculate($values),
                    ]);
                });

            return $payroll;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft payroll created.')]);

        return to_route('payrolls.show', $payroll);
    }

    public function show(Request $request, Payroll $payroll): Response
    {
        Gate::authorize('view', $payroll);
        $payroll->load(['company:id,company_code,company_name,address,logo', 'items']);

        return Inertia::render('payrolls/Show', [
            'payroll' => $payroll,
            'weekdayCount' => PayrollPeriod::weekdays($payroll->period_start->toImmutable(), $payroll->period_end->toImmutable()),
            'can' => [
                'edit' => $request->user()->can('update', $payroll),
                'finalize' => $request->user()->can('finalize', $payroll),
                'reopen' => $request->user()->can('reopen', $payroll),
                'viewPayslip' => $request->user()->can('viewPayslip', $payroll),
                'printPayslip' => $request->user()->can('printPayslip', $payroll),
                'export' => $request->user()->can('export', $payroll),
            ],
        ]);
    }

    public function update(UpdatePayrollRequest $request, Payroll $payroll, PayrollCalculator $calculator): RedirectResponse
    {
        DB::transaction(function () use ($request, $payroll, $calculator): void {
            $lockedPayroll = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);
            abort_unless($lockedPayroll->isDraft(), 409, __('Finalized payrolls cannot be changed.'));

            foreach ($request->validated('items') as $submittedItem) {
                $item = $lockedPayroll->items()->lockForUpdate()->findOrFail($submittedItem['id']);
                $inputs = collect($submittedItem)->only(PayrollItem::INPUT_FIELDS)->all();
                $values = [...$item->only(['monthly_basic_salary', 'hours_per_day', 'payroll_days_basis', 'monthly_workdays_divisor', 'annual_workdays_divisor', 'regular_ot_multiplier', 'special_holiday_multiplier', 'special_holiday_ot_multiplier', 'regular_holiday_multiplier', 'regular_holiday_ot_multiplier']), ...$inputs];
                $item->update([...$inputs, ...$calculator->calculate($values)]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll inputs saved.')]);

        return to_route('payrolls.show', $payroll);
    }

    public function finalize(Request $request, Payroll $payroll): RedirectResponse
    {
        Gate::authorize('finalize', $payroll);

        DB::transaction(function () use ($request, $payroll): void {
            $lockedPayroll = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);
            abort_unless($lockedPayroll->isDraft(), 409);
            abort_if($lockedPayroll->items()->doesntExist(), 422, __('A payroll must contain at least one employee.'));
            $lockedPayroll->update(['status' => Payroll::STATUS_FINALIZED, 'finalized_by' => $request->user()->id, 'finalized_at' => now()]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll finalized and locked.')]);

        return to_route('payrolls.show', $payroll);
    }

    public function reopen(Payroll $payroll): RedirectResponse
    {
        Gate::authorize('reopen', $payroll);

        DB::transaction(function () use ($payroll): void {
            $lockedPayroll = Payroll::query()->lockForUpdate()->findOrFail($payroll->id);
            abort_if($lockedPayroll->isDraft(), 409);
            $lockedPayroll->update(['status' => Payroll::STATUS_DRAFT, 'finalized_by' => null, 'finalized_at' => null]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll reopened for editing.')]);

        return to_route('payrolls.show', $payroll);
    }

    /** @return array<int, array{id: int, company_code: string, company_name: string}> */
    private function companyOptions(User $user): array
    {
        return $this->companyQuery($user)->get()->map(fn (Company $company): array => $company->only(['id', 'company_code', 'company_name']))->all();
    }

    /** @return Builder<Company> */
    private function companyQuery(User $user): Builder
    {
        return Company::query()->select(['id', 'company_code', 'company_name'])->active()
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas('users', fn (Builder $query) => $query->whereKey($user->id)))
            ->orderBy('company_name');
    }

    private function selectedCompany(Request $request, User $user): ?Company
    {
        $companies = $this->companyQuery($user)->get();
        $requestedCompanyId = $request->integer('company_id');
        $company = $requestedCompanyId > 0
            ? $companies->firstWhere('id', $requestedCompanyId)
            : ($companies->firstWhere('id', (int) $request->session()->get('current_company_id')) ?? $companies->first());
        abort_if($requestedCompanyId > 0 && $company === null, 403);

        if ($company !== null) {
            $request->session()->put('current_company_id', $company->id);
        }

        return $company;
    }
}
