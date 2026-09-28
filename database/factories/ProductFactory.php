<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(20, 60) * 1000;

        return [
            'name' => fake()->unique()->words(3, true),
            'category_id' => Category::factory(),
            'description' => fake()->sentence(),
            'base_price' => $price,
            'selling_price' => $price,
            'cost_price' => (int) ($price * 0.4),
            'sku' => null,
            'is_available' => true,
            'is_featured' => false,
            'display_order' => 0,
            'prep_time_minutes' => 5,
        ];
    }

    /**
     * A stock-tracked product with the given quantity on hand.
     */
    public function stocked(int $onHand = 20, int $minStock = 5): static
    {
        return $this->afterCreating(function (Product $product) use ($onHand, $minStock): void {
            $inventory = new Inventory(['product_id' => $product->id, 'min_stock' => $minStock]);
            $inventory->quantity_on_hand = $onHand;
            $inventory->save();
        });
    }
}
