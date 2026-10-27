<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Models\WorkBoard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkBoard>
 */
class WorkBoardFactory extends Factory
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
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'color' => 'blue',
            'status' => WorkBoard::STATUS_ACTIVE,
            'created_by' => User::factory(),
        ];
    }
}
