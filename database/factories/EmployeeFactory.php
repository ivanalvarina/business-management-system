<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
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
            'employee_number' => fake()->unique()->bothify('EMP-####'),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'hire_date' => fake()->dateTimeBetween('-10 years', 'now'),
            'department' => fake()->randomElement(['Administration', 'Finance', 'Operations', 'Sales']),
            'job_title' => fake()->jobTitle(),
            'employment_type' => fake()->randomElement(['regular', 'probationary', 'contractual']),
            'pay_frequency' => 'semi-monthly',
            'basic_salary' => fake()->randomFloat(2, 15000, 100000),
            'tin' => fake()->optional()->numerify('###-###-###-###'),
            'sss_number' => fake()->optional()->numerify('##-#######-#'),
            'philhealth_number' => fake()->optional()->numerify('##-#########-#'),
            'pagibig_number' => fake()->optional()->numerify('####-####-####'),
            'bank_name' => fake()->optional()->company(),
            'bank_account_name' => fake()->optional()->name(),
            'bank_account_number' => fake()->optional()->numerify('############'),
            'status' => Employee::STATUS_ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => Employee::STATUS_INACTIVE,
        ]);
    }
}
