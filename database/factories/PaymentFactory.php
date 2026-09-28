<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'amount' => 50000,
            'method' => PaymentMethod::Cash,
            'status' => Payment::STATUS_COMPLETED,
            'paid_amount' => 50000,
            'change_amount' => 0,
            'paid_at' => now(),
        ];
    }
}
