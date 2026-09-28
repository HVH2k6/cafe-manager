<?php

namespace App\Events;

use App\OrderItemStatus;
use Illuminate\Broadcasting\Channel;

/**
 * A new order was placed: the kitchen sounds an alert, order lists refresh.
 */
class OrderCreated extends OrderBroadcast
{
    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [$this->kitchenChannel(), $this->ordersChannel()];
    }

    public function broadcastAs(): string
    {
        return 'order.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            ...$this->orderSummary(),
            'item_count' => (int) $this->order->items()->where('status', '!=', OrderItemStatus::Cancelled)->sum('quantity'),
            'total_amount' => (int) $this->order->total_amount,
        ];
    }
}
