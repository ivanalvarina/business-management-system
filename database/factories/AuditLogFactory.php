<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'module' => 'companies',
            'action' => 'created',
            'subject_type' => null,
            'subject_id' => null,
            'old_values' => null,
            'new_values' => ['name' => fake()->company()],
            'ip_address' => fake()->ipv4(),
        ];
    }
}
