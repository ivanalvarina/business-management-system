<?php

namespace Database\Factories;

use App\Models\ProductService;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_service_id' => ProductService::factory(),
            'description' => fake()->sentence(),
            'quantity' => '1.0000',
            'unit' => fake()->randomElement(['pc', 'set', 'hour', 'day', 'lot']),
            'unit_price' => '100.00',
            'discount' => '0.00',
            'tax' => '12.00',
            'line_total' => '112.00',
            'sort_order' => 0,
        ];
    }
}
