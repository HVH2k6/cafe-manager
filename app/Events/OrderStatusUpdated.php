<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;

/**
 * An order changed. `change` says what: its status, its items (added / removed),
 * its payment or its discount — all but the first can leave the status as it was.
 */
class OrderStatusUpdated extends OrderBroadcast
{
    public const CHANGE_STATUS = 'status';

    public const CHANGE_ITEMS = 'items';

    /** Payment recorded or refunded, payOS QR code created or cancelled. */
    public const CHANGE_PAYMENT = 'payment';

    /** Discount changed, so the total did. */
    public const CHANGE_DISCOUNT = 'discount';

    /**
     * Something other than the status changed: tell the pages showing the order.
     */
    public static function changed(Order $order, string $change): void
    {
        static::send($order, $order->status->value, $order->status->value, $change);
    }

    public function __construct(
        Order $order,
        public string $oldStatus,
        public string $newStatus,
        public string $change = self::CHANGE_STATUS,
    ) {
        parent::__construct($order);
    }

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [$this->kitchenChannel(), $this->ordersChannel(), $this->orderChannel(), ...$this->customerChannels()];
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            ...$this->orderSummary(),
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'change' => $this->change,
        ];
    }
}
