<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\OrderItemStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => 1,
            'unit_price' => 30000,
            'status' => OrderItemStatus::Pending,
        ];
    }

    public function status(OrderItemStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
