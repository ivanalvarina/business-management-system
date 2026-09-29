<?php

namespace Database\Factories;

use App\Models\ClientPurchaseOrder;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
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
            'product_id' => ProductService::factory(['type' => ProductService::TYPE_PRODUCT]),
            'type' => InventoryMovement::TYPE_OUT,
            'quantity' => 1,
            'quantity_before' => 10,
            'quantity_after' => 9,
            'reference_type' => ClientPurchaseOrder::class,
            'reference_id' => ClientPurchaseOrder::factory(),
            'performed_by' => User::factory(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
