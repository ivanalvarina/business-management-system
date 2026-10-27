<?php

namespace Database\Factories;

use App\Models\TaskLabel;
use App\Models\WorkBoard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskLabel>
 */
class TaskLabelFactory extends Factory
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
            'name' => fake()->unique()->word(),
            'color' => 'blue',
        ];
    }
}
