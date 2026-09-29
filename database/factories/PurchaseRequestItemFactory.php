<?php

namespace Database\Factories;

use App\Models\ProductService;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequestItem>
 */
class PurchaseRequestItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 100, 5000);
        $tax = fake()->randomFloat(2, 0, 500);

        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'product_service_id' => ProductService::factory(),
            'description' => fake()->sentence(),
            'quantity' => $quantity,
            'unit' => 'pcs',
            'unit_price' => $unitPrice,
            'tax' => $tax,
            'line_total' => round(($quantity * $unitPrice) + $tax, 2),
            'sort_order' => 0,
        ];
    }
}
