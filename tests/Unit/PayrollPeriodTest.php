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

it('calculates contractual payroll periods and pay dates', function () {
    $firstHalf = PayrollPeriod::forCutoff('2026-09', Payroll::GROUP_CONTRACTUAL, Payroll::CUTOFF_FIRST_HALF);
    $secondHalf = PayrollPeriod::forCutoff('2026-09', Payroll::GROUP_CONTRACTUAL, Payroll::CUTOFF_SECOND_HALF);

    expect($firstHalf['period_start']->toDateString())->toBe('2026-09-01')
        ->and($firstHalf['period_end']->toDateString())->toBe('2026-09-15')
        ->and($firstHalf['pay_date']->toDateString())->toBe('2026-09-30')
        ->and($secondHalf['period_start']->toDateString())->toBe('2026-09-16')
        ->and($secondHalf['period_end']->toDateString())->toBe('2026-09-30')
        ->and($secondHalf['pay_date']->toDateString())->toBe('2026-10-15');
});

it('uses month end when a contractual first-half payout month has no thirtieth', function () {
    $period = PayrollPeriod::forCutoff('2026-02', Payroll::GROUP_CONTRACTUAL, Payroll::CUTOFF_FIRST_HALF);

    expect($period['period_start']->toDateString())->toBe('2026-02-01')
        ->and($period['period_end']->toDateString())->toBe('2026-02-15')
        ->and($period['pay_date']->toDateString())->toBe('2026-03-02');
});

it('rolls a contractual second-half payout into the next year', function () {
    $period = PayrollPeriod::forCutoff('2026-12', Payroll::GROUP_CONTRACTUAL, Payroll::CUTOFF_SECOND_HALF);

    expect($period['period_start']->toDateString())->toBe('2026-12-16')
        ->and($period['period_end']->toDateString())->toBe('2026-12-31')
        ->and($period['pay_date']->toDateString())->toBe('2027-01-15');
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
