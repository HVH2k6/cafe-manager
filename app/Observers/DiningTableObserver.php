<?php

namespace App\Observers;

use App\Events\TableUpdated;
use App\Models\DiningTable;
use App\TableStatus;

/**
 * Broadcasts every change to a table so floor plans stay live. Table statuses
 * are always changed through the model (PlaceOrder, FreeTable, the status
 * buttons), never with a bulk query update, so nothing is missed.
 */
class DiningTableObserver
{
    public function created(DiningTable $table): void
    {
        TableUpdated::for($table);
    }

    public function updated(DiningTable $table): void
    {
        $previous = $table->getPrevious()['status'] ?? null;
        $oldStatus = $previous instanceof TableStatus ? $previous->value : $previous;

        TableUpdated::for($table, $table->wasChanged('status') ? $oldStatus : null);
    }

    public function deleted(DiningTable $table): void
    {
        TableUpdated::for($table, deleted: true);
    }
}
