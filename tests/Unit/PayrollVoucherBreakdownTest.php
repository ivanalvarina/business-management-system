<?php

use App\Models\PayrollItem;
use App\Support\PayrollVoucherBreakdown;

test('it includes only deductions with a positive amount', function () {
    $item = new PayrollItem([
        'absence_deduction' => 500,
        'late_deduction' => 75,
        'sss_contribution' => 0,
        'philhealth_contribution' => null,
        'pagibig_contribution' => 0,
        'withholding_tax' => 100,
        'other_deduction' => 800,
        'other_deductions' => [
            [
                'type' => 'cash_advance',
                'amount' => '500.00',
                'reference' => 'CA-001',
                'remarks' => null,
            ],
            [
                'type' => 'company_loan',
                'amount' => '300.00',
                'reference' => null,
                'remarks' => null,
            ],
        ],
    ]);

    $deductions = (new PayrollVoucherBreakdown)->deductions($item);

    expect($deductions)->toBe([
        ['label' => 'Absence', 'amount' => 500.0],
        ['label' => 'Late', 'amount' => 75.0],
        ['label' => 'Withholding tax', 'amount' => 100.0],
        ['label' => 'Cash advance', 'amount' => 500.0],
        ['label' => 'Company loan', 'amount' => 300.0],
    ]);
});

test('it retains the legacy other deduction when no itemized breakdown exists', function () {
    $item = new PayrollItem([
        'other_deduction' => 250,
        'other_deductions' => null,
    ]);

    expect((new PayrollVoucherBreakdown)->deductions($item))->toBe([
        ['label' => 'Other deduction', 'amount' => 250.0],
    ]);
});

test('it returns no deduction rows when every amount is zero or null', function () {
    $item = new PayrollItem([
        'absence_deduction' => 0,
        'late_deduction' => null,
        'sss_contribution' => 0,
        'philhealth_contribution' => null,
        'pagibig_contribution' => 0,
        'withholding_tax' => null,
        'other_deduction' => 0,
    ]);

    expect((new PayrollVoucherBreakdown)->deductions($item))->toBe([]);
});
