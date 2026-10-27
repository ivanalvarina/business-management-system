<?php

namespace Database\Factories;

use App\Models\WorkBoard;
use App\Models\WorkBoardColumn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkBoardColumn>
 */
class WorkBoardColumnFactory extends Factory
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
            'color' => 'slate',
            'position' => 0,
            'is_done' => false,
        ];
    }
}
