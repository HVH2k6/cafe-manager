<?php

namespace App\Events;

use App\Events\Concerns\SendsAfterCommit;
use App\Models\DiningTable;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A table changed (seated by an order, freed, cleaned, added, edited or removed):
 * the floor plan refreshes. Sent by DiningTableObserver.
 */
class TableUpdated implements ShouldBroadcastNow
{
    use SendsAfterCommit;

    public function __construct(
        public int $tableId,
        public int $tableNo,
        public ?string $status,
        public ?string $oldStatus = null,
    ) {}

    public static function for(DiningTable $table, ?string $oldStatus = null, bool $deleted = false): void
    {
        static::send($table->id, $table->table_no, $deleted ? null : $table->status->value, $oldStatus);
    }

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('tables')];
    }

    public function broadcastAs(): string
    {
        return 'table.updated';
    }

    /**
     * @return array{id: int, table_no: int, status: ?string, old_status: ?string}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->tableId,
            'table_no' => $this->tableNo,
            'status' => $this->status,
            'old_status' => $this->oldStatus,
        ];
    }
}
