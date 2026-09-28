<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\User;
use App\OrderItemStatus;
use App\OrderStatus;
use App\TableStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes an order once it is both served and paid (in either order: takeaway
 * customers pay first, tables pay last): takes the held stock out (logged as
 * sales) and marks the table for cleaning.
 */
class CompleteOrder
{
    public function __construct(private StockReservation $stock) {}

    /**
     * Complete the order if it is served and paid; otherwise leave it as it is.
     * Called after serving and after recording a payment.
     *
     * @param  User|null  $by  null when a payOS webhook settled the order
     */
    public function ifSettled(Order $order, ?User $by): bool
    {
        if ($order->fresh()->status !== OrderStatus::Served || ! $order->isPaid()) {
            return false;
        }

        $this->handle($order, $by);

        return true;
    }

    /**
     * @throws ValidationException
     */
    public function handle(Order $order, ?User $by): Order
    {
        return DB::transaction(function () use ($order, $by): Order {
            $order = Order::with('items.bundle.items')->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::Served) {
                throw ValidationException::withMessages(['status' => "Đơn {$order->order_number} chưa mang ra hết món, chưa hoàn thành được."]);
            }

            if (! $order->isPaid()) {
                throw ValidationException::withMessages(['status' => "Đơn {$order->order_number} chưa thanh toán."]);
            }

            $this->stock->consume($order->items->where('status', '!=', OrderItemStatus::Cancelled), $order, $by);

            $order->update([
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
            ]);

            FreeTable::after($order, TableStatus::Dirty);

            return $order;
        });
    }
}
