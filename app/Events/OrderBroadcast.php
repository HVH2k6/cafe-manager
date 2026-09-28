<?php

namespace App\Events;

use App\Events\Concerns\SendsAfterCommit;
use App\Models\Order;
use App\Models\QrSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Base of the real-time order events (step 7A). Events are only signals:
 * pages reload their data from the server, so payloads stay small.
 *
 *  - Sent right away (ShouldBroadcastNow): no queue worker needed.
 *  - Sent only after the surrounding transaction commits; nothing is sent if it rolls back.
 *  - A broadcasting failure (Reverb down) is reported but never breaks the order flow;
 *    pages fall back to polling.
 */
abstract class OrderBroadcast implements ShouldBroadcastNow
{
    use SendsAfterCommit;

    public function __construct(public Order $order) {}

    protected function kitchenChannel(): PrivateChannel
    {
        return new PrivateChannel('kitchen');
    }

    protected function ordersChannel(): PrivateChannel
    {
        return new PrivateChannel('orders');
    }

    protected function orderChannel(): PrivateChannel
    {
        return new PrivateChannel("order.{$this->order->id}");
    }

    /**
     * Public channels of the customers' phones following this order by QR (none for staff orders).
     *
     * @return list<Channel>
     */
    protected function customerChannels(): array
    {
        return QrSession::where('order_id', $this->order->id)->get()
            ->map(fn (QrSession $session): Channel => new Channel($session->channelName()))
            ->all();
    }

    /**
     * Fields every order event carries.
     *
     * @return array{id: int, order_number: string, place: string, status: string, status_label: string}
     */
    protected function orderSummary(): array
    {
        $this->order->loadMissing('diningTable');

        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'place' => $this->order->placeLabel(),
            'status' => $this->order->status->value,
            'status_label' => $this->order->status->label(),
        ];
    }
}
