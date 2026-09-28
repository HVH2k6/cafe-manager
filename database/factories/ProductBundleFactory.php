<?php

namespace Database\Factories;

use App\Models\ProductBundle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBundle>
 */
class ProductBundleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Combo '.fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'bundle_price' => 50000,
            'individual_price' => 60000,
            'discount_amount' => 10000,
            'is_active' => true,
            'display_order' => 0,
        ];
    }
}
