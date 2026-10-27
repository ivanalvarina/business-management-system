<?php

namespace App\Support;

class PayrollCalculator
{
    /**
     * @param  array<string, int|float|string|null>  $values
     * @return array<string, float>
     */
    public function calculate(array $values): array
    {
        // Monthly salary and semi-monthly basic
        $monthlySalary = (float) $values['monthly_basic_salary'];
        $periodBasicPay = $monthlySalary / 2;

        // Allowance is stored per semi-monthly cutoff
        $allowance = (float) ($values['allowance'] ?? 0);

        // Convert semi-monthly allowance to monthly equivalent
        $monthlyAllowance = $allowance * 2;

        // Configurable payroll divisors
        $hoursPerDay = (float) $values['hours_per_day'];
        $monthlyWorkdays = (float) $values['monthly_workdays_divisor'];

        // Include allowance in daily/hourly rates
        $monthlyRateBasis = $monthlySalary + $monthlyAllowance;

        $dailyRate = $monthlyWorkdays > 0
            ? $monthlyRateBasis / $monthlyWorkdays
            : 0.0;

        $hourlyRate = $hoursPerDay > 0
            ? $dailyRate / $hoursPerDay
            : 0.0;

        // Attendance deductions
        $absenceDeduction = $dailyRate
            * (float) ($values['absent_days'] ?? 0);

        $lateDeduction = $hourlyRate
            * (float) ($values['late_hours'] ?? 0);

        // Overtime and holiday payments
        $regularOtPay = $hourlyRate
            * (float) ($values['regular_ot_hours'] ?? 0)
            * (float) $values['regular_ot_multiplier'];

        $specialHolidayPay = $hourlyRate
            * (float) ($values['special_holiday_hours'] ?? 0)
            * (float) $values['special_holiday_multiplier'];

        $regularHolidayPay = $hourlyRate
            * (float) ($values['regular_holiday_hours'] ?? 0)
            * (float) $values['regular_holiday_multiplier'];

        // Other earnings
        $salaryAdjustment = (float) ($values['salary_adjustment'] ?? 0);

        // Gross pay includes allowance separately
        $grossPay = $periodBasicPay
            + $allowance
            + $regularOtPay
            + $specialHolidayPay
            + $regularHolidayPay
            + $salaryAdjustment;

        // Government and other deductions
        $governmentDeductions =
            (float) ($values['sss_contribution'] ?? 0)
            + (float) ($values['philhealth_contribution'] ?? 0)
            + (float) ($values['pagibig_contribution'] ?? 0)
            + (float) ($values['withholding_tax'] ?? 0)
            + (float) ($values['other_deduction'] ?? 0);

        $totalDeductions = $absenceDeduction
            + $lateDeduction
            + $governmentDeductions;

        $netPay = $grossPay - $totalDeductions;

        // Preserve existing database output fields
        return array_map(
            fn (float $amount): float => round($amount, 2),
            [
                'period_basic_pay' => $periodBasicPay,
                'daily_rate' => $dailyRate,
                'hourly_rate' => $hourlyRate,
                'absence_deduction' => $absenceDeduction,
                'late_deduction' => $lateDeduction,
                'regular_ot_pay' => $regularOtPay,
                'special_holiday_pay' => $specialHolidayPay,
                'special_holiday_ot_pay' => 0.0,
                'regular_holiday_pay' => $regularHolidayPay,
                'regular_holiday_ot_pay' => 0.0,
                'gross_pay' => $grossPay,
                'total_deductions' => $totalDeductions,
                'net_pay' => $netPay,
            ]
        );
    }
}
