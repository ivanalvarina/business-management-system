<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quotationDate = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'quotation_no' => DocumentSequence::nextQuotationNumber($quotationDate->format('Y-m-d')),
            'company_id' => Company::factory(),
            'client_id' => Client::factory(),
            'quotation_date' => $quotationDate->format('Y-m-d'),
            'valid_until' => fake()->dateTimeBetween($quotationDate, '+2 months')->format('Y-m-d'),
            'currency' => 'PHP',
            'status' => Quotation::STATUS_DRAFT,
            'notes' => fake()->optional()->sentence(),
            'terms_conditions' => fake()->optional()->paragraph(),
            'quotation_template_id' => null,
            'quotation_template_snapshot' => null,
            'subtotal' => '1000.00',
            'discount' => '0.00',
            'tax_amount' => '120.00',
            'total_amount' => '1120.00',
            'created_by' => User::factory(),
        ];
    }
}
