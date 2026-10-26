<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PayrollSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollSetting>
 */
class PayrollSettingFactory extends Factory
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
            ...PayrollSetting::defaults(),
        ];
    }
}
