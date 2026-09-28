<?php

namespace Database\Factories;

use App\Models\DiningTable;
use App\TableStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_no' => fake()->unique()->numberBetween(1, 999),
            'capacity' => fake()->randomElement([2, 4, 6]),
            'location' => null,
            'section' => fake()->randomElement(['Tầng 1', 'Tầng 2', 'Sân vườn']),
            'status' => TableStatus::Available,
            'notes' => null,
        ];
    }

    public function status(TableStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
