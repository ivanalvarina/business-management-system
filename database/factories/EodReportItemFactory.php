<?php

namespace Database\Factories;

use App\Models\EodReport;
use App\Models\EodReportItem;
use App\Models\WorkTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EodReportItem>
 */
class EodReportItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'eod_report_id' => EodReport::factory(),
            'work_task_id' => WorkTask::factory(),
            'accomplishment' => fake()->paragraph(),
            'minutes_spent' => fake()->optional()->numberBetween(15, 480),
        ];
    }
}
