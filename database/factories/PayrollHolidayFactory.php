<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PayrollHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollHoliday>
 */
class PayrollHolidayFactory extends Factory
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
            'date' => fake()->unique()->date(),
            'name' => fake()->words(3, true),
        ];
    }
}
