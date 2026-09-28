<?php

namespace Database\Factories;

use App\ContractType;
use App\Models\Employee;
use App\Models\User;
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
            'user_id' => User::factory(),
            'position' => fake()->randomElement(['Pha chế', 'Phục vụ', 'Thu ngân', 'Quản lý ca']),
            'department' => fake()->randomElement(['Quầy bar', 'Sảnh', null]),
            'salary' => fake()->numberBetween(50, 150) * 100_000,
            'hire_date' => fake()->dateTimeBetween('-3 years', 'now'),
            'contract_type' => fake()->randomElement(ContractType::cases()),
            'is_active' => true,
            'notes' => null,
        ];
    }
}
