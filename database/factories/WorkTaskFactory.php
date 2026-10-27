<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkBoard;
use App\Models\WorkBoardColumn;
use App\Models\WorkTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkTask>
 */
class WorkTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_board_id' => WorkBoard::factory(),
            'work_board_column_id' => WorkBoardColumn::factory(),
            'assigned_to' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'priority' => WorkTask::PRIORITY_NORMAL,
            'due_date' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'position' => 0,
            'completed_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
