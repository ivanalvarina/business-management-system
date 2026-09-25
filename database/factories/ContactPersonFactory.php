<?php

namespace Database\Factories;

use App\Models\ContactPerson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactPerson>
 */
class ContactPersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'position' => fake()->optional()->jobTitle(),
            'department' => fake()->optional()->randomElement(['Finance', 'Operations', 'Procurement', 'Sales']),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'mobile' => fake()->optional()->phoneNumber(),
            'is_primary' => false,
        ];
    }
}
