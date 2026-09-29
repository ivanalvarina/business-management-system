<?php

namespace Database\Factories;

use App\Models\ProductService;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationItem>
 */
class QuotationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(4, 1, 10);
        $unitPrice = fake()->randomFloat(2, 100, 10000);
        $discount = fake()->randomFloat(2, 0, 100);
        $tax = fake()->randomFloat(2, 0, 500);

        return [
            'quotation_id' => Quotation::factory(),
            'product_service_id' => ProductService::factory(),
            'description' => fake()->sentence(),
            'quantity' => $quantity,
            'unit' => fake()->randomElement(['pc', 'set', 'hour', 'day', 'lot']),
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'tax' => $tax,
            'line_total' => round(($quantity * $unitPrice) - $discount + $tax, 2),
            'sort_order' => 0,
        ];
    }
}
