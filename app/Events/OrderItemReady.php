<?php

namespace App\Events;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Broadcasting\Channel;

/**
 * The kitchen finished one item while others of the order are still being
 * made: waiters can already take it out ("Đã mang món ra" serves the ready
 * items). When the last item is done, OrderReady is sent instead.
 */
class OrderItemReady extends OrderBroadcast
{
    public function __construct(Order $order, public OrderItem $item)
    {
        parent::__construct($order);
    }

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [$this->ordersChannel(), $this->orderChannel(), ...$this->customerChannels()];
    }

    public function broadcastAs(): string
    {
        return 'order.item.ready';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $summary = $this->orderSummary();
        $this->item->loadMissing(['product', 'bundle']);

        return [
            ...$summary,
            'item_id' => $this->item->id,
            'item' => $this->item->name(),
            'quantity' => $this->item->quantity,
            'message' => "{$summary['place']} — {$this->item->name()} ×{$this->item->quantity} đã xong, mang ra được ({$summary['order_number']}).",
            'url' => route('orders.show', $this->order),
        ];
    }
}
