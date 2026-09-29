<?php

namespace Database\Factories;

use App\Models\DocumentSequence;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivingReceipt>
 */
class ReceivingReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $receivedDate = fake()->dateTimeBetween('-1 month', '+1 month');
        $purchaseOrder = PurchaseOrder::factory()->create([
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);

        return [
            'rr_no' => DocumentSequence::nextReceivingReceiptNumber($receivedDate->format('Y-m-d')),
            'company_id' => $purchaseOrder->company_id,
            'purchase_order_id' => $purchaseOrder->id,
            'vendor_id' => $purchaseOrder->vendor_id,
            'invoice_no' => fake()->optional()->bothify('DR-####'),
            'received_date' => $receivedDate->format('Y-m-d'),
            'received_by_name' => fake()->name(),
            'checked_by_name' => fake()->optional()->name(),
            'status' => ReceivingReceipt::STATUS_RECEIVED,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
