<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchaseOrderDate = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'po_no' => DocumentSequence::nextPurchaseOrderNumber($purchaseOrderDate->format('Y-m-d')),
            'company_id' => Company::factory(),
            'vendor_id' => Vendor::factory(),
            'po_date' => $purchaseOrderDate->format('Y-m-d'),
            'expected_delivery' => fake()->dateTimeBetween($purchaseOrderDate, '+2 months')->format('Y-m-d'),
            'currency' => 'PHP',
            'subtotal' => '1000.00',
            'discount' => '0.00',
            'tax_amount' => '120.00',
            'total_amount' => '1120.00',
            'status' => PurchaseOrder::STATUS_DRAFT,
            'notes' => fake()->optional()->sentence(),
            'terms_conditions' => fake()->optional()->paragraph(),
            'created_by' => User::factory(),
        ];
    }
}
