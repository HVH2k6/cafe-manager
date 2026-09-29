<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\TableStatus;

/**
 * Releases an order's table once the order is closed, unless another open
 * order still uses it.
 */
class FreeTable
{
    public static function after(Order $order, TableStatus $status): void
    {
        if ($order->table_id === null) {
            return;
        }

        $tableStillInUse = Order::open()
            ->where('table_id', $order->table_id)
            ->whereKeyNot($order->id)
            ->exists();

        // Through the model (not a query update) so DiningTableObserver broadcasts it to the floor plan.
        if (! $tableStillInUse) {
            $order->diningTable?->update(['status' => $status]);
        }
    }
}
