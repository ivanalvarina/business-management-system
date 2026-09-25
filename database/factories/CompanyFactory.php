<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_code' => fake()->unique()->bothify('CMP-####'),
            'company_name' => fake()->company(),
            'trade_name' => fake()->optional()->companySuffix(),
            'tin' => fake()->optional()->numerify('###-###-###-###'),
            'email' => fake()->optional()->companyEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'logo' => null,
            'status' => Company::STATUS_ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Company::STATUS_INACTIVE,
        ]);
    }
}
