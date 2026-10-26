<?php

use App\Support\PayrollCalculator;

test('it calculates the client semi-monthly payroll format', function () {
    $result = (new PayrollCalculator)->calculate([
        'monthly_basic_salary' => 30000,
        'hours_per_day' => 8,
        'payroll_days_basis' => 13,
        'monthly_workdays_divisor' => 26,
        'annual_workdays_divisor' => 261,
        'regular_ot_multiplier' => 1.25,
        'special_holiday_multiplier' => 1.30,
        'special_holiday_ot_multiplier' => 1.69,
        'regular_holiday_multiplier' => 2.00,
        'regular_holiday_ot_multiplier' => 2.60,
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
    ]);

    expect($result)
        ->period_basic_pay->toBe(15000.0)
        ->daily_rate->toBe(1153.85)
        ->hourly_rate->toBe(144.23)
        ->absence_deduction->toBe(1153.85)
        ->late_deduction->toBe(288.46)
        ->regular_ot_pay->toBe(540.87)
        ->special_holiday_pay->toBe(1500.0)
        ->special_holiday_ot_pay->toBe(0.0)
        ->regular_holiday_pay->toBe(2307.69)
        ->regular_holiday_ot_pay->toBe(0.0)
        ->gross_pay->toBe(20203.56)
        ->total_deductions->toBe(2992.31)
        ->net_pay->toBe(17211.25);
});
