<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
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
    $included = Employee::factory()->for($company)->create(['employee_number' => 'EMP-1001', 'basic_salary' => 30000]);
    Employee::factory()->for($company)->inactive()->create();
    Employee::factory()->for($company)->create(['pay_frequency' => Employee::PAY_FREQUENCY_MONTHLY]);
    Employee::factory()->create();

    $this->actingAs($user)
        ->post(route('payrolls.store'), ['company_id' => $company->id, 'payroll_month' => '2026-10', 'payday' => 25])
        ->assertRedirect();

    $payroll = Payroll::query()->sole();
    $item = $payroll->items()->sole();

    expect($payroll->period_start->toDateString())->toBe('2026-10-01')
        ->and($payroll->period_end->toDateString())->toBe('2026-10-15')
        ->and($payroll->pay_date->toDateString())->toBe('2026-10-25')
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
    Employee::factory()->for($company)->create();

    $this->actingAs($user)
        ->post(route('payrolls.store'), ['company_id' => $company->id, 'payroll_month' => '2026-10', 'payday' => 10])
        ->assertRedirect();

    $payroll = Payroll::query()->sole();

    expect($payroll->period_start->toDateString())->toBe('2026-10-16')
        ->and($payroll->period_end->toDateString())->toBe('2026-10-31')
        ->and($payroll->pay_date->toDateString())->toBe('2026-11-10');
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
            'allowance' => 500,
        ]]])
        ->assertRedirect(route('payrolls.show', $payroll));

    $item->refresh();
    expect($item->gross_pay)->toBe(20203.56)
        ->and($item->total_deductions)->toBe(2992.31)
        ->and($item->net_pay)->toBe(17211.25);
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

test('authorized users can preview an employee payslip as a pdf', function () {
    $user = userWithPermissions('payslips.view');
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);
    $item = payrollItemFor($payroll);

    $response = $this->actingAs($user)->get(route('payrolls.payslips.show', [$payroll, $item]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
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

test('authorized users can export an Excel-compatible payroll report with totals', function () {
    $user = userWithPermissions('payrolls.export');
    $payroll = Payroll::factory()->create();
    $user->companies()->attach($payroll->company_id);
    $employee = Employee::factory()->for($payroll->company)->create();
    PayrollItem::factory()->for($payroll)->for($employee)->create([
        'employee_number' => 'EMP-CSV-001',
        'employee_name' => '=2+2',
    ]);

    $response = $this->actingAs($user)->get(route('payrolls.export', $payroll));

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $content = $response->streamedContent();
    $lines = preg_split('/\r\n|\n|\r/', trim($content));
    $totalRow = str_getcsv($lines[array_key_last($lines)], ',', '"', '');

    expect($content)->toContain('Employee No.', 'EMP-CSV-001', "'=2+2")
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
});

function payrollItemFor(Payroll $payroll): PayrollItem
{
    $employee = Employee::factory()->for($payroll->company)->create(['basic_salary' => 30000]);

    return PayrollItem::factory()->for($payroll)->for($employee)->create();
}

/** @param array<string, int|float> $overrides */
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
