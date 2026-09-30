<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\QuotationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationTemplate>
 */
class QuotationTemplateFactory extends Factory
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
            'name' => 'Default quotation template',
            'original_filename' => 'quotation-template.pdf',
            'stored_path' => 'quotation-templates/template.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_type' => 'pdf',
            'config' => [
                'fields' => [
                    'quotation_no' => ['page' => 1, 'x' => 540, 'y' => 120],
                ],
            ],
            'is_active' => true,
            'uploaded_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
