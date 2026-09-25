<?php

namespace Database\Factories;

use App\Models\ProductService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductService>
 */
class ProductServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PS-####'),
            'type' => fake()->randomElement([ProductService::TYPE_PRODUCT, ProductService::TYPE_SERVICE]),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'unit' => fake()->randomElement(['pc', 'set', 'hour', 'day', 'lot']),
            'default_price' => fake()->randomFloat(2, 0, 100000),
            'status' => ProductService::STATUS_ACTIVE,
        ];
    }
}
