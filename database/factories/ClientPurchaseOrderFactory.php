<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientPurchaseOrder;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPurchaseOrder>
 */
class ClientPurchaseOrderFactory extends Factory
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
            'client_id' => Client::factory(),
            'quotation_id' => null,
            'client_po_no' => fake()->unique()->bothify('CPO-####'),
            'po_date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'currency' => 'PHP',
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'status' => ClientPurchaseOrder::STATUS_RECEIVED,
            'received_by' => User::factory(),
            'received_at' => now(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
