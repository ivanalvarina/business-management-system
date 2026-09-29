<?php

namespace Database\Factories;

use App\Models\ClientPurchaseOrder;
use App\Models\ClientPurchaseOrderItem;
use App\Models\ProductService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPurchaseOrderItem>
 */
class ClientPurchaseOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->randomFloat(2, 100, 1000);

        return [
            'client_purchase_order_id' => ClientPurchaseOrder::factory(),
            'product_service_id' => ProductService::factory(),
            'description' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit' => 'pc',
            'unit_price' => $unitPrice,
            'discount' => 0,
            'tax' => 0,
            'line_total' => $quantity * $unitPrice,
            'sort_order' => 0,
        ];
    }
}
