<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'documentable_type' => Company::class,
            'documentable_id' => Company::factory(),
            'document_type' => 'General',
            'original_filename' => 'document.pdf',
            'stored_path' => fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'expiration_date' => fake()->optional()->date(),
            'uploaded_by' => User::factory(),
        ];
    }
}
