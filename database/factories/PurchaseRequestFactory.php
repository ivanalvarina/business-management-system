<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requestDate = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'pr_no' => DocumentSequence::nextPurchaseRequestNumber($requestDate->format('Y-m-d')),
            'company_id' => Company::factory(),
            'vendor_id' => Vendor::factory(),
            'request_date' => $requestDate->format('Y-m-d'),
            'date_required' => fake()->dateTimeBetween($requestDate, '+2 months')->format('Y-m-d'),
            'client_project' => fake()->optional()->company(),
            'requested_by_name' => fake()->name(),
            'checked_by_name' => fake()->optional()->name(),
            'noted_by_name' => fake()->optional()->name(),
            'currency' => 'PHP',
            'subtotal' => '1000.00',
            'tax_amount' => '120.00',
            'total_amount' => '1120.00',
            'status' => PurchaseRequest::STATUS_DRAFT,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
