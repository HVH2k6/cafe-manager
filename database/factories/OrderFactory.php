<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\OrderType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.now()->year.'-'.fake()->unique()->numerify('9###'),
            'user_id' => User::factory(),
            'table_id' => null,
            'order_type' => OrderType::Takeaway,
            'status' => OrderStatus::Confirmed,
            'subtotal' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
