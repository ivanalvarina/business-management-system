<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payroll>
 */
class PayrollFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'payroll_group' => Payroll::GROUP_REGULAR,
            'cutoff_type' => Payroll::CUTOFF_FIRST_HALF,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-15',
            'pay_date' => '2026-10-25',
            'status' => Payroll::STATUS_DRAFT,
            'created_by' => User::factory(),
            'finalized_by' => null,
            'finalized_at' => null,
        ];
    }
}
