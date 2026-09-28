<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;

/**
 * Every item of the order is done: waiters get a pop-up with a sound,
 * the kitchen highlights the ticket.
 */
class OrderReady extends OrderBroadcast
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
        return 'order.ready';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $summary = $this->orderSummary();

        return [
            ...$summary,
            'message' => "{$summary['place']} — món đã xong, mang ra cho khách ({$summary['order_number']}).",
            'url' => route('orders.show', $this->order),
        ];
    }
}
