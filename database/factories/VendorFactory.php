<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_code' => fake()->unique()->bothify('VEN-####'),
            'vendor_name' => fake()->company(),
            'trade_name' => fake()->optional()->companySuffix(),
            'tin' => fake()->optional()->numerify('###-###-###-###'),
            'email' => fake()->optional()->companyEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'vendor_category_id' => VendorCategory::factory(),
            'status' => Vendor::STATUS_ACTIVE,
            'created_by' => User::factory(),
        ];
    }
}
