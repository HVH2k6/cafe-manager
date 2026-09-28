<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
            'min_stock' => 5,
            'max_stock' => 100,
            'reorder_level' => 20,
            'unit_of_measure' => 'piece',
        ];
    }
}
