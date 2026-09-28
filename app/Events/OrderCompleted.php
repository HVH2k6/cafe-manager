<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;

/**
 * The order is paid and served: the kitchen drops it, lists refresh.
 */
class OrderCompleted extends OrderBroadcast
{
    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [$this->kitchenChannel(), $this->ordersChannel(), $this->orderChannel()];
    }

    public function broadcastAs(): string
    {
        return 'order.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->orderSummary();
    }
}
