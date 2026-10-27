<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\EodReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EodReport>
 */
class EodReportFactory extends Factory
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
            'user_id' => User::factory(),
            'work_date' => now()->toDateString(),
            'summary' => fake()->sentence(),
            'blockers' => null,
            'next_plan' => fake()->optional()->sentence(),
            'status' => EodReport::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
