<?php

namespace Database\Factories;

use App\Models\DocumentSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentSequence>
 */
class DocumentSequenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'series' => 'QT',
            'year' => (int) now()->format('Y'),
            'last_number' => fake()->numberBetween(0, 999),
        ];
    }
}
