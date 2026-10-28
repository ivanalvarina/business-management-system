<?php

use App\Models\Payroll;
use App\Support\PayrollPeriod;

it('calculates regular payroll schedules and moves weekend pay dates to the next working day', function () {
    $firstHalf = PayrollPeriod::forCutoff('2026-10', Payroll::GROUP_REGULAR, Payroll::CUTOFF_FIRST_HALF);
    $secondHalf = PayrollPeriod::forCutoff('2026-10', Payroll::GROUP_REGULAR, Payroll::CUTOFF_SECOND_HALF);

    expect($firstHalf['period_start']->toDateString())->toBe('2026-10-01')
        ->and($firstHalf['period_end']->toDateString())->toBe('2026-10-15')
        ->and($firstHalf['pay_date']->toDateString())->toBe('2026-10-26')
        ->and($secondHalf['period_start']->toDateString())->toBe('2026-10-16')
        ->and($secondHalf['period_end']->toDateString())->toBe('2026-10-31')
        ->and($secondHalf['pay_date']->toDateString())->toBe('2026-11-10');
});

it('calculates contractual schedules and moves weekend pay dates to the next working day', function () {
    $firstHalf = PayrollPeriod::forCutoff('2026-08', Payroll::GROUP_CONTRACTUAL, Payroll::CUTOFF_FIRST_HALF);
    $secondHalf = PayrollPeriod::forCutoff('2026-05', Payroll::GROUP_CONTRACTUAL, Payroll::CUTOFF_SECOND_HALF);

    expect($firstHalf['period_start']->toDateString())->toBe('2026-08-01')
        ->and($firstHalf['period_end']->toDateString())->toBe('2026-08-15')
        ->and($firstHalf['pay_date']->toDateString())->toBe('2026-08-17')
        ->and($secondHalf['period_start']->toDateString())->toBe('2026-05-16')
        ->and($secondHalf['period_end']->toDateString())->toBe('2026-05-31')
        ->and($secondHalf['pay_date']->toDateString())->toBe('2026-06-01');
});

it('continues past saved holidays until the next working day', function () {
    $period = PayrollPeriod::forCutoff(
        '2026-10',
        Payroll::GROUP_REGULAR,
        Payroll::CUTOFF_FIRST_HALF,
        ['2026-10-26'],
    );

    expect($period['pay_date']->toDateString())->toBe('2026-10-27');
});
