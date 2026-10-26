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
        'other_deduction' => 0,
    ]);

    $deductions = (new PayrollVoucherBreakdown)->deductions($item);

    expect($deductions)->toBe([
        ['label' => 'Absence', 'amount' => 500.0],
        ['label' => 'Late', 'amount' => 75.0],
        ['label' => 'Withholding tax', 'amount' => 100.0],
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
