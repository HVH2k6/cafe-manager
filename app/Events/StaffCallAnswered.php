<?php

namespace App\Events;

use App\Events\Concerns\SendsAfterCommit;
use App\Models\QrNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A staff member took a customer's call: the customer's phone shows that
 * someone is coming, and the pop-up disappears from the other staff screens.
 */
class StaffCallAnswered implements ShouldBroadcastNow
{
    use SendsAfterCommit;

    public function __construct(public QrNotification $call, public string $staffName) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel($this->call->qrSession->channelName()), new PrivateChannel('orders')];
    }

    public function broadcastAs(): string
    {
        return 'staff.answered';
    }

    /**
     * @return array{id: int, message: string}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->call->id,
            'message' => "{$this->staffName} đang tới bàn bạn.",
        ];
    }
}
