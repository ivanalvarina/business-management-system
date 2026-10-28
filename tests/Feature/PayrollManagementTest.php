<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollHoliday;
use App\Models\PayrollItem;
use App\Models\User;

test('users without permission cannot access payroll', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('payrolls.index'))
        ->assertForbidden();
});

test('payroll creation loads only active semi-monthly employees for the first-half cutoff', function () {
    $user = userWithPermissions(['payrolls.create', 'payrolls.view']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $included = Employee::factory()->for($company)->create([
        'employee_number' => 'EMP-1001',
        'employment_type' => Employee::EMPLOYMENT_REGULAR,
        'basic_salary' => 30000,
    ]);
    $probationary = Employee::factory()->for($company)->create([
        'employment_type' => Employee::EMPLOYMENT_PROBATIONARY,
    ]);
    Employee::factory()->for($company)->create([
        'employment_type' => Employee::EMPLOYMENT_CONTRACTUAL,
    ]);
    Employee::factory()->for($company)->inactive()->create();
    Employee::factory()->for($company)->create(['pay_frequency' => Employee::PAY_FREQUENCY_MONTHLY]);
    Employee::factory()->create();

    $this->actingAs($user)
        ->post(route('payrolls.store'), [
            'company_id' => $company->id,
            'payroll_month' => '2026-10',
            'payroll_group' => Payroll::GROUP_REGULAR,
            'cutoff_type' => Payroll::CUTOFF_FIRST_HALF,
        ])
        ->assertRedirect();

    $payroll = Payroll::query()->sole();
    $item = $payroll->items()->where('employee_id', $included->id)->sole();

    expect($payroll->period_start->toDateString())->toBe('2026-10-01')
        ->and($payroll->period_end->toDateString())->toBe('2026-10-15')
        ->and($payroll->pay_date->toDateString())->toBe('2026-10-26')
        ->and($payroll->payroll_group)->toBe(Payroll::GROUP_REGULAR)
        ->and($payroll->cutoff_type)->toBe(Payroll::CUTOFF_FIRST_HALF)
        ->and($payroll->items()->pluck('employee_id')->sort()->values()->all())
        ->toBe(collect([$included->id, $probationary->id])->sort()->values()->all())
        ->and($item->employee_id)->toBe($included->id)
        ->and($item->payroll_days_basis)->toEqual(13)
        ->and($item->monthly_workdays_divisor)->toEqual(26)
        ->and($item->days_worked)->toEqual(13)
        ->and($item->period_basic_pay)->toEqual(15000);
});

test('the second-half cutoff is paid on the tenth of the next month', function () {
    $user = userWithPermissions(['payrolls.create', 'payrolls.view']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    Employee::factory()->for($company)->create([
        'employment_type' => Employee::EMPLOYMENT_REGULAR,
    ]);

    $this->actingAs($user)
        ->post(route('payrolls.store'), [
            'company_id' => $company->id,
            'payroll_month' => '2026-10',
            'payroll_group' => Payroll::GROUP_REGULAR,
            'cutoff_type' => Payroll::CUTOFF_SECOND_HALF,
        ])
        ->assertRedirect();

    $payroll = Payroll::query()->sole();

    expect($payroll->period_start->toDateString())->toBe('2026-10-16')
        ->and($payroll->period_end->toDateString())->toBe('2026-10-31')
        ->and($payroll->pay_date->toDateString())->toBe('2026-11-10');
});

test('payroll creation skips saved company holidays after a weekend', function () {
    $user = userWithPermissions(['payrolls.create', 'payrolls.view']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    Employee::factory()->for($company)->create([
        'employment_type' => Employee::EMPLOYMENT_REGULAR,
    ]);
    PayrollHoliday::factory()->for($company)->create(['date' => '2026-10-26']);

    $this->actingAs($user)
        ->post(route('payrolls.store'), [
            'company_id' => $company->id,
            'payroll_month' => '2026-10',
            'payroll_group' => Payroll::GROUP_REGULAR,
            'cutoff_type' => Payroll::CUTOFF_FIRST_HALF,
        ])
        ->assertRedirect();

    expect(Payroll::query()->sole()->pay_date->toDateString())->toBe('2026-10-27');
});

test('contractual payroll loads only contractual employees', function () {
    $user = userWithPermissions(['payrolls.create', 'payrolls.view']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $contractual = Employee::factory()->for($company)->create([
        'employment_type' => Employee::EMPLOYMENT_CONTRACTUAL,
    ]);
    Employee::factory()->for($company)->create([
        'employment_type' => Employee::EMPLOYMENT_REGULAR,
    ]);

    $this->actingAs($user)
        ->post(route('payrolls.store'), [
            'company_id' => $company->id,
            'payroll_month' => '2026-08',
            'payroll_group' => Payroll::GROUP_CONTRACTUAL,
            'cutoff_type' => Payroll::CUTOFF_FIRST_HALF,
        ])
        ->assertRedirect();

    $payroll = Payroll::query()->sole();

    expect($payroll->pay_date->toDateString())->toBe('2026-08-17')
        ->and($payroll->items()->sole()->employee_id)->toBe($contractual->id);
});

test('regular and contractual payrolls may use the same cutoff but duplicate groups may not', function () {
    $user = userWithPermissions(['payrolls.create', 'payrolls.view']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    Employee::factory()->for($company)->create(['employment_type' => Employee::EMPLOYMENT_REGULAR]);
    Employee::factory()->for($company)->create(['employment_type' => Employee::EMPLOYMENT_CONTRACTUAL]);
    $payload = [
        'company_id' => $company->id,
        'payroll_month' => '2026-10',
        'cutoff_type' => Payroll::CUTOFF_FIRST_HALF,
    ];

    $this->actingAs($user)->post(route('payrolls.store'), [
        ...$payload,
        'payroll_group' => Payroll::GROUP_REGULAR,
    ])->assertRedirect();
    $this->actingAs($user)->post(route('payrolls.store'), [
        ...$payload,
        'payroll_group' => Payroll::GROUP_CONTRACTUAL,
    ])->assertRedirect();
    $this->actingAs($user)->post(route('payrolls.store'), [
        ...$payload,
        'payroll_group' => Payroll::GROUP_REGULAR,
    ])->assertSessionHasErrors('payroll_month');

    expect(Payroll::query()->count())->toBe(2);
});

test('payroll creation rejects an employee group with no matching employees', function () {
    $user = userWithPermissions(['payrolls.create', 'payrolls.view']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    Employee::factory()->for($company)->create(['employment_type' => Employee::EMPLOYMENT_REGULAR]);

    $this->actingAs($user)
        ->post(route('payrolls.store'), [
            'company_id' => $company->id,
            'payroll_month' => '2026-10',
            'payroll_group' => Payroll::GROUP_CONTRACTUAL,
            'cutoff_type' => Payroll::CUTOFF_FIRST_HALF,
        ])
        ->assertSessionHasErrors('payroll_group');

    expect(Payroll::query()->doesntExist())->toBeTrue();
});

test('authorized users can save payroll inputs and computed amounts', function () {
    $user = userWithPermissions(['payrolls.view', 'payrolls.edit']);
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $payroll = Payroll::factory()->for($company)->for($user, 'creator')->create();
    $item = payrollItemFor($payroll);

    $this->actingAs($user)
        ->put(route('payrolls.update', $payroll), ['items' => [[
            'id' => $item->id,
            'days_worked' => 13,
            'absent_days' => 1,
            'late_hours' => 2,
            'regular_ot_hours' => 3,
            'special_holiday_hours' => 8,
            'regular_holiday_hours' => 8,
            'salary_adjustment' => 355,
            'sss_contribution' => 600,
            'philhealth_contribution' => 300,
            'pagibig_contribution' => 200,
            'withholding_tax' => 400,
            'other_deduction' => 50,
            'other_deductions' => [
                [
                    'type' => 'cash_advance',
                    'amount' => 30,
                    'reference' => 'CA-001',
                    'remarks' => 'First installment',
                ],
                [
                    'type' => 'equipment_accountability',
                    'amount' => 20,
                    'reference' => null,
                    'remarks' => null,
                ],
            ],
            'allowance' => 500,
        ]]])
        ->assertRedirect(route('payrolls.show', $payroll));

    $item->refresh();
    expect($item->gross_pay)->toBe(20348.51)
        ->and((float) $item->other_deduction)->toBe(50.0)
        ->and($item->other_deductions)->toBe([
            [
                'type' => 'cash_advance',
                'amount' => '30.00',
                'reference' => 'CA-001',
                'remarks' => 'First installment',
            ],
            [
                'type' => 'equipment_accountability',
                'amount' => '20.00',
                'reference' => null,
                'remarks' => null,
            ],
        ])
        ->and($item->total_deductions)->toBe(3040.38)
        ->and($item->net_pay)->toBe(17308.13);
});

test('payroll itemized deductions reject unsupported types and zero amounts', function () {
    $user = userWithPermissions('payrolls.edit');
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);
    $item = payrollItemFor($payroll);

    $payload = payrollInputPayload($item->id, [
        'other_deductions' => [[
            'type' => 'personal_expense',
            'amount' => 0,
            'reference' => null,
            'remarks' => null,
        ]],
    ]);

    $this->actingAs($user)
        ->put(route('payrolls.update', $payroll), $payload)
        ->assertSessionHasErrors([
            'items.0.other_deductions.0.type',
            'items.0.other_deductions.0.amount',
        ]);

    expect($item->fresh()->other_deductions)->toBeNull();
});

test('worked days use the payroll basis while absences use scheduled weekdays', function () {
    $user = userWithPermissions('payrolls.edit');
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);
    $item = payrollItemFor($payroll);
    $payload = payrollInputPayload($item->id, ['days_worked' => 14, 'absent_days' => 12]);

    $this->actingAs($user)
        ->put(route('payrolls.update', $payroll), $payload)
        ->assertSessionHasErrors(['items.0.days_worked', 'items.0.absent_days']);
});

test('finalized payroll is locked until an authorized user reopens it', function () {
    $user = userWithPermissions(['payrolls.view', 'payrolls.edit', 'payrolls.finalize', 'payrolls.reopen']);
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);
    $item = payrollItemFor($payroll);

    $this->actingAs($user)->patch(route('payrolls.finalize', $payroll))->assertRedirect();
    expect($payroll->fresh()->status)->toBe(Payroll::STATUS_FINALIZED);

    $this->actingAs($user)
        ->put(route('payrolls.update', $payroll), payrollInputPayload($item->id))
        ->assertForbidden();

    $this->actingAs($user)->patch(route('payrolls.reopen', $payroll))->assertRedirect();
    expect($payroll->fresh()->status)->toBe(Payroll::STATUS_DRAFT);
});

test('authorized users can delete a draft payroll run and its payroll items', function () {
    $user = userWithPermissions(['payrolls.view', 'payrolls.delete']);
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);
    $item = payrollItemFor($payroll);

    $this->actingAs($user)
        ->delete(route('payrolls.destroy', $payroll))
        ->assertRedirect(route('payrolls.index', ['company_id' => $payroll->company_id]));

    $this->assertModelMissing($payroll);
    $this->assertModelMissing($item);
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'module' => 'payrolls',
        'action' => 'deleted',
        'subject_id' => $payroll->id,
    ]);
});

test('users without delete permission cannot delete a payroll run', function () {
    $user = userWithPermissions('payrolls.view');
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);

    $this->actingAs($user)
        ->delete(route('payrolls.destroy', $payroll))
        ->assertForbidden();

    $this->assertModelExists($payroll);
});

test('finalized payroll runs cannot be deleted', function () {
    $user = userWithPermissions('payrolls.delete');
    $payroll = Payroll::factory()->create(['status' => Payroll::STATUS_FINALIZED]);
    $user->companies()->attach($payroll->company_id);

    $this->actingAs($user)
        ->delete(route('payrolls.destroy', $payroll))
        ->assertForbidden();

    $this->assertModelExists($payroll);
});

test('users cannot delete payroll runs from an inaccessible company', function () {
    $user = userWithPermissions('payrolls.delete');
    $accessibleCompany = Company::factory()->create();
    $user->companies()->attach($accessibleCompany);
    $payroll = Payroll::factory()->create();

    $this->actingAs($user)
        ->delete(route('payrolls.destroy', $payroll))
        ->assertForbidden();

    $this->assertModelExists($payroll);
});

test('finalizing payroll assigns sequential voucher numbers only to contractual employees', function () {
    $user = userWithPermissions('payrolls.finalize');
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $firstPayroll = Payroll::factory()->for($company)->for($user, 'creator')->create();
    $secondPayroll = Payroll::factory()->for($company)->for($user, 'creator')->create([
        'period_start' => '2026-10-16',
        'period_end' => '2026-10-31',
        'pay_date' => '2026-11-10',
    ]);
    $contractualEmployee = Employee::factory()->for($company)->create([
        'employment_type' => Employee::EMPLOYMENT_CONTRACTUAL,
    ]);
    $regularEmployee = Employee::factory()->for($company)->create([
        'employment_type' => Employee::EMPLOYMENT_REGULAR,
    ]);
    $firstContractualItem = PayrollItem::factory()
        ->for($firstPayroll)
        ->for($contractualEmployee)
        ->create(['employment_type' => Employee::EMPLOYMENT_CONTRACTUAL]);
    $regularItem = PayrollItem::factory()
        ->for($firstPayroll)
        ->for($regularEmployee)
        ->create(['employment_type' => Employee::EMPLOYMENT_REGULAR]);
    $secondContractualItem = PayrollItem::factory()
        ->for($secondPayroll)
        ->for($contractualEmployee)
        ->create(['employment_type' => Employee::EMPLOYMENT_CONTRACTUAL]);

    $this->actingAs($user)
        ->patch(route('payrolls.finalize', $firstPayroll))
        ->assertRedirect(route('payrolls.show', $firstPayroll));
    $this->actingAs($user)
        ->patch(route('payrolls.finalize', $secondPayroll))
        ->assertRedirect(route('payrolls.show', $secondPayroll));

    expect($firstContractualItem->fresh()->voucher_no)->toBe('26001')
        ->and($regularItem->fresh()->voucher_no)->toBeNull()
        ->and($secondContractualItem->fresh()->voucher_no)->toBe('26002');
});

test('authorized users can preview an employee payslip as a pdf', function () {
    $user = userWithPermissions('payslips.view');
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);
    $item = payrollItemFor($payroll);

    $response = $this->actingAs($user)->get(route('payrolls.payslips.show', [$payroll, $item]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});

test('contractual employee voucher contains both copies on one pdf page', function () {
    $user = userWithPermissions('payslips.view');
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);
    $employee = Employee::factory()->for($payroll->company)->create([
        'employment_type' => Employee::EMPLOYMENT_CONTRACTUAL,
    ]);
    $item = PayrollItem::factory()
        ->for($payroll)
        ->for($employee)
        ->create([
            'employee_number' => 'CONT-001',
            'employment_type' => Employee::EMPLOYMENT_CONTRACTUAL,
            'voucher_no' => '26001',
            'absence_deduction' => 500,
            'late_deduction' => 75,
            'sss_contribution' => 100,
            'philhealth_contribution' => 100,
            'pagibig_contribution' => 100,
            'withholding_tax' => 100,
            'other_deduction' => 50,
            'total_deductions' => 1025,
            'net_pay' => 13975,
        ]);

    $response = $this->actingAs($user)->get(route('payrolls.payslips.show', [$payroll, $item]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="CONT-001-voucher.pdf"');
    expect($response->getContent())->toStartWith('%PDF-')
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()))->toBe(1);
});

test('authorized users can preview all employee payslips in one pdf', function () {
    $user = userWithPermissions('payslips.print');
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);
    payrollItemFor($payroll);
    payrollItemFor($payroll);

    $response = $this->actingAs($user)->get(route('payrolls.payslips.bulk', $payroll));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-')
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()))->toBe(2);
});

test('bulk payslip preview remains a valid pdf when payroll has no employees', function () {
    $user = userWithPermissions('payslips.print');
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);

    $response = $this->actingAs($user)->get(route('payrolls.payslips.bulk', $payroll));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});

test('authorized users can preview a paginated payroll register pdf', function () {
    $user = userWithPermissions('payrolls.export');
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);

    Employee::factory()
        ->count(30)
        ->for($payroll->company)
        ->create()
        ->each(fn (Employee $employee) => PayrollItem::factory()->for($payroll)->for($employee)->create());

    $response = $this->actingAs($user)->get(route('payrolls.register.show', $payroll));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader(
            'Content-Disposition',
            'inline; filename="'.strtolower($payroll->company->company_code).'-'.$payroll->period_start->format('Y-m-d').'-'.$payroll->period_end->format('Y-m-d').'-payroll-register.pdf"',
        );
    expect($response->getContent())->toStartWith('%PDF-')
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()))->toBeGreaterThan(1);
});

test('authorized users can export an Excel-compatible payroll report with totals', function () {
    $user = userWithPermissions('payrolls.export');
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);
    $employee = Employee::factory()->for($payroll->company)->create();
    PayrollItem::factory()->for($payroll)->for($employee)->create([
        'employee_number' => 'EMP-CSV-001',
        'employee_name' => '=2+2',
        'other_deduction' => 750,
        'other_deductions' => [[
            'type' => 'cash_advance',
            'amount' => '750.00',
            'reference' => 'CA-009',
            'remarks' => null,
        ]],
    ]);

    $response = $this->actingAs($user)->get(route('payrolls.export', $payroll));

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $content = $response->streamedContent();
    $lines = preg_split('/\r\n|\n|\r/', trim($content));
    $totalRow = str_getcsv($lines[array_key_last($lines)], ',', '"', '');

    expect($content)->toContain('Employee No.', 'EMP-CSV-001', "'=2+2", 'Cash advance #CA-009: 750.00')
        ->and($totalRow[1])->toBe('TOTAL')
        ->and($totalRow[25])->toBe('15000.00');
});

test('payroll data is restricted to assigned companies', function () {
    $user = userWithPermissions(['payrolls.view', 'payrolls.export', 'payslips.view', 'payslips.print']);
    $payroll = Payroll::factory()->create();
    $item = payrollItemFor($payroll);

    $this->actingAs($user)->get(route('payrolls.show', $payroll))->assertForbidden();
    $this->actingAs($user)->get(route('payrolls.payslips.show', [$payroll, $item]))->assertForbidden();
    $this->actingAs($user)->get(route('payrolls.payslips.bulk', $payroll))->assertForbidden();
    $this->actingAs($user)->get(route('payrolls.export', $payroll))->assertForbidden();
    $this->actingAs($user)->get(route('payrolls.register.show', $payroll))->assertForbidden();
});

function payrollItemFor(Payroll $payroll): PayrollItem
{
    $employee = Employee::factory()->for($payroll->company)->create(['basic_salary' => 30000]);

    return PayrollItem::factory()->for($payroll)->for($employee)->create();
}

/** @param array<string, mixed> $overrides */
function payrollInputPayload(int $itemId, array $overrides = []): array
{
    return ['items' => [[
        'id' => $itemId,
        'days_worked' => 13,
        'absent_days' => 0,
        'late_hours' => 0,
        'regular_ot_hours' => 0,
        'special_holiday_hours' => 0,
        'regular_holiday_hours' => 0,
        'salary_adjustment' => 0,
        'sss_contribution' => 0,
        'philhealth_contribution' => 0,
        'pagibig_contribution' => 0,
        'withholding_tax' => 0,
        'other_deduction' => 0,
        'allowance' => 0,
        ...$overrides,
    ]]];
}
