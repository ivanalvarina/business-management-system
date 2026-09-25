<?php

namespace Database\Factories;

use App\Models\VendorCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorCategory>
 */
class VendorCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'status' => VendorCategory::STATUS_ACTIVE,
        ];
    }
}
