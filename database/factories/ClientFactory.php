<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_code' => fake()->unique()->bothify('CL-####'),
            'client_name' => fake()->company(),
            'trade_name' => fake()->optional()->companySuffix(),
            'tin' => fake()->optional()->numerify('###-###-###-###'),
            'email' => fake()->optional()->companyEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'billing_address' => fake()->optional()->address(),
            'shipping_address' => fake()->optional()->address(),
            'status' => Client::STATUS_ACTIVE,
            'created_by' => User::factory(),
        ];
    }
}
