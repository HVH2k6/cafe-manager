<?php

namespace App\Events;

use App\Events\Concerns\SendsAfterCommit;
use App\Models\QrNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A customer pressed "Gọi nhân viên" on their phone: every staff screen with
 * `orders.view` shows a pop-up until someone answers it.
 */
class StaffCalled implements ShouldBroadcastNow
{
    use SendsAfterCommit;

    public function __construct(public QrNotification $call) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('orders')];
    }

    public function broadcastAs(): string
    {
        return 'staff.called';
    }

    /**
     * @return array{id: int, place: string, message: string, answer_url: string}
     */
    public function broadcastWith(): array
    {
        $this->call->loadMissing('qrSession.diningTable');

        return [
            'id' => $this->call->id,
            'place' => 'Bàn '.$this->call->qrSession->diningTable->table_no,
            'message' => $this->call->message,
            'answer_url' => route('qr-calls.answer', $this->call),
        ];
    }
}
