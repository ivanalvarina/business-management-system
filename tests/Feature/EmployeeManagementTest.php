<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;

test('users without permission cannot access employee management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('employees.index'))
        ->assertForbidden();
});

test('authorized users can create payroll-ready employee master data', function () {
    $user = userWithPermissions(['employees.view', 'employees.create']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);

    $this->actingAs($user)
        ->post(route('employees.store'), employeePayload($company, [
            'employee_number' => 'EMP-0001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('employees', [
        'company_id' => $company->id,
        'employee_number' => 'EMP-0001',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'basic_salary' => 30000,
        'status' => Employee::STATUS_ACTIVE,
    ]);
});

test('employee numbers are unique within a company', function () {
    $user = userWithPermissions('employees.create');
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user->companies()->attach([$company->id, $otherCompany->id]);
    Employee::factory()->for($company)->create(['employee_number' => 'EMP-0001']);

    $this->actingAs($user)
        ->post(route('employees.store'), employeePayload($company))
        ->assertSessionHasErrors('employee_number');

    $this->actingAs($user)
        ->post(route('employees.store'), employeePayload($otherCompany))
        ->assertRedirect();

    expect(Employee::where('employee_number', 'EMP-0001')->count())->toBe(2);
});

test('employees are restricted to companies the user can access', function () {
    $user = userWithPermissions(['employees.view', 'employees.edit']);
    $assignedCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user->companies()->attach($assignedCompany);
    $visibleEmployee = Employee::factory()->for($assignedCompany)->create([
        'first_name' => 'Visible',
        'middle_name' => null,
        'last_name' => 'Employee',
    ]);
    $hiddenEmployee = Employee::factory()->for($otherCompany)->create([
        'first_name' => 'Hidden',
        'middle_name' => null,
        'last_name' => 'Employee',
    ]);

    $this->actingAs($user)
        ->get(route('employees.index', ['company_id' => $assignedCompany->id]))
        ->assertOk()
        ->assertSee('Visible Employee')
        ->assertDontSee('Hidden Employee');

    $this->actingAs($user)
        ->get(route('employees.edit', $hiddenEmployee))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('employees.index', ['company_id' => $otherCompany->id]))
        ->assertForbidden();

    expect($visibleEmployee->company_id)->toBe($assignedCompany->id);
});

test('authorized users can update employee pay and employment details', function () {
    $user = userWithPermissions(['employees.view', 'employees.edit']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $employee = Employee::factory()->for($company)->create();

    $this->actingAs($user)
        ->put(route('employees.update', $employee), employeePayload($company, [
            'employee_number' => $employee->employee_number,
            'job_title' => 'Senior Payroll Assistant',
            'basic_salary' => '45000.00',
            'status' => Employee::STATUS_INACTIVE,
        ]))
        ->assertRedirect(route('employees.index', ['company_id' => $company->id]));

    expect($employee->fresh()->job_title)->toBe('Senior Payroll Assistant')
        ->and($employee->fresh()->basic_salary)->toBe('45000.00')
        ->and($employee->fresh()->status)->toBe(Employee::STATUS_INACTIVE);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeePayload(Company $company, array $overrides = []): array
{
    return [
        'company_id' => $company->id,
        'employee_number' => 'EMP-0001',
        'first_name' => 'Maria',
        'middle_name' => 'Santos',
        'last_name' => 'Reyes',
        'email' => 'maria.reyes@example.com',
        'phone' => '09171234567',
        'address' => 'Makati City',
        'birth_date' => '1990-05-15',
        'hire_date' => '2026-01-15',
        'department' => 'Finance',
        'job_title' => 'Payroll Assistant',
        'employment_type' => Employee::EMPLOYMENT_REGULAR,
        'pay_frequency' => Employee::PAY_FREQUENCY_SEMI_MONTHLY,
        'basic_salary' => '30000.00',
        'tin' => '123-456-789-000',
        'sss_number' => '12-3456789-0',
        'philhealth_number' => '12-345678901-2',
        'pagibig_number' => '1234-5678-9012',
        'bank_name' => 'MVP Bank',
        'bank_account_name' => 'Maria Santos Reyes',
        'bank_account_number' => '123456789012',
        'status' => Employee::STATUS_ACTIVE,
        ...$overrides,
    ];
}
