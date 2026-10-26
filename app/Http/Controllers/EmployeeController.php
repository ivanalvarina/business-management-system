<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Employee::class);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $company = $this->selectedCompany($request, $request->user());

        return Inertia::render('employees/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status === '' ? 'all' : $status,
                'company_id' => $company?->id,
            ],
            'companies' => $this->companyOptions($request->user()),
            'employees' => Employee::query()
                ->select(['id', 'company_id', 'employee_number', 'first_name', 'middle_name', 'last_name', 'department', 'job_title', 'employment_type', 'pay_frequency', 'basic_salary', 'status'])
                ->with('company:id,company_code,company_name')
                ->when($company !== null, fn (Builder $query) => $query->whereBelongsTo($company))
                ->when($company === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('employee_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%");
                }))
                ->when(in_array($status, Employee::statuses(), true), fn (Builder $query) => $query->where('status', $status))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Employee $employee): array => $this->summaryPayload($employee)),
            'can' => [
                'create' => $request->user()->can('create', Employee::class),
                'edit' => $request->user()->can('employees.edit'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Employee::class);

        $companies = $this->companyOptions($request->user());

        return Inertia::render('employees/Create', [
            'companies' => $companies,
            'defaultCompanyId' => $this->selectedCompany($request, $request->user())?->id ?? $companies[0]['id'] ?? null,
            'employmentTypes' => Employee::employmentTypes(),
            'payFrequencies' => Employee::payFrequencies(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request, AuditLogger $audit): RedirectResponse
    {
        $employee = Employee::create($request->validated());

        $audit->record(
            module: 'employees',
            action: 'created',
            subject: $employee,
            newValues: $employee->only($this->auditedAttributes()),
            request: $request,
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee created.')]);

        return to_route('employees.edit', $employee);
    }

    /**
     * Display the specified resource.
     */
    public function edit(Request $request, Employee $employee): Response
    {
        Gate::authorize('update', $employee);

        return Inertia::render('employees/Edit', [
            'employee' => $this->formPayload($employee),
            'companies' => $this->companyOptions($request->user()),
            'employmentTypes' => Employee::employmentTypes(),
            'payFrequencies' => Employee::payFrequencies(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee, AuditLogger $audit): RedirectResponse
    {
        $oldValues = $employee->only($this->auditedAttributes());
        $employee->update($request->validated());

        $audit->recordChanges(
            module: 'employees',
            action: 'updated',
            subject: $employee,
            oldValues: $oldValues,
            newValues: $employee->only(array_intersect($this->auditedAttributes(), array_keys($employee->getChanges()))),
            request: $request,
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return to_route('employees.index', ['company_id' => $employee->company_id]);
    }

    /**
     * @return array<int, array{id: int, company_code: string, company_name: string}>
     */
    private function companyOptions(User $user): array
    {
        return $this->companyQuery($user)
            ->get()
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'company_code' => $company->company_code,
                'company_name' => $company->company_name,
            ])
            ->all();
    }

    /**
     * @return Builder<Company>
     */
    private function companyQuery(User $user): Builder
    {
        return Company::query()
            ->select(['id', 'company_code', 'company_name'])
            ->active()
            ->when(! $user->hasGlobalCompanyAccess(), fn (Builder $query) => $query->whereHas(
                'users',
                fn (Builder $query) => $query->whereKey($user->id),
            ))
            ->orderBy('company_name')
            ->orderBy('id');
    }

    private function selectedCompany(Request $request, User $user): ?Company
    {
        $companies = $this->companyQuery($user)->get();
        $requestedCompanyId = $request->integer('company_id');

        if ($requestedCompanyId > 0) {
            $company = $companies->firstWhere('id', $requestedCompanyId);
            abort_unless($company !== null, 403);
        } else {
            $company = $companies->firstWhere('id', (int) $request->session()->get('current_company_id'))
                ?? $companies->first();
        }

        if ($company !== null) {
            $request->session()->put('current_company_id', $company->id);
        }

        return $company;
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'company' => $employee->company,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->fullName(),
            'department' => $employee->department,
            'job_title' => $employee->job_title,
            'employment_type' => $employee->employment_type,
            'pay_frequency' => $employee->pay_frequency,
            'basic_salary' => $employee->basic_salary,
            'status' => $employee->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(Employee $employee): array
    {
        return [
            ...$employee->only([
                'id',
                'company_id',
                'employee_number',
                'first_name',
                'middle_name',
                'last_name',
                'email',
                'phone',
                'address',
                'department',
                'job_title',
                'employment_type',
                'pay_frequency',
                'basic_salary',
                'tin',
                'sss_number',
                'philhealth_number',
                'pagibig_number',
                'bank_name',
                'bank_account_name',
                'bank_account_number',
                'status',
            ]),
            'birth_date' => $employee->birth_date?->toDateString(),
            'hire_date' => $employee->hire_date->toDateString(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function auditedAttributes(): array
    {
        return [
            'company_id',
            'employee_number',
            'first_name',
            'middle_name',
            'last_name',
            'email',
            'phone',
            'department',
            'job_title',
            'employment_type',
            'pay_frequency',
            'basic_salary',
            'status',
        ];
    }
}
