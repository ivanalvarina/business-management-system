<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollSetting;
use App\Support\PayrollCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollItem>
 */
class PayrollItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $values = [
            'monthly_basic_salary' => 30000,
            ...PayrollSetting::defaults(),
            'days_worked' => 11,
            'absent_days' => 0,
            'late_hours' => 0,
            'regular_ot_hours' => 0,
            'special_holiday_hours' => 0,
            'special_holiday_ot_hours' => 0,
            'regular_holiday_hours' => 0,
            'regular_holiday_ot_hours' => 0,
            'salary_adjustment' => 0,
            'sss_contribution' => 0,
            'philhealth_contribution' => 0,
            'pagibig_contribution' => 0,
            'withholding_tax' => 0,
            'other_deduction' => 0,
            'allowance' => 0,
        ];

        return [
            'payroll_id' => Payroll::factory(),
            'employee_id' => Employee::factory(),
            'employee_number' => fake()->unique()->bothify('EMP-####'),
            'employee_name' => fake()->name(),
            'department' => 'Operations',
            'job_title' => fake()->jobTitle(),
            'employment_type' => Employee::EMPLOYMENT_REGULAR,
            'voucher_no' => null,
            'pay_frequency' => Employee::PAY_FREQUENCY_SEMI_MONTHLY,
            'bank_name' => 'MVP Bank',
            'bank_account_last_four' => fake()->numerify('####'),
            ...$values,
            ...(new PayrollCalculator)->calculate($values),
        ];
    }
}
