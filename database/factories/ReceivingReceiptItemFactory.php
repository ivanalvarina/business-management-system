<?php

namespace Database\Factories;

use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReceipt;
use App\Models\ReceivingReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivingReceiptItem>
 */
class ReceivingReceiptItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receiving_receipt_id' => ReceivingReceipt::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'quantity' => fake()->randomFloat(4, 1, 5),
            'description' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
