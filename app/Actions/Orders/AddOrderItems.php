<?php

namespace App\Actions\Orders;

use App\Events\OrderStatusUpdated;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds items to an order that is still open (a table ordering more). New
 * items go to the kitchen, so a ready or served order moves back to preparing.
 */
class AddOrderItems
{
    public function __construct(
        private BuildOrderItems $buildOrderItems,
        private StockReservation $stock,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lines
     *
     * @throws ValidationException
     */
    public function handle(Order $order, array $lines): Order
    {
        return DB::transaction(function () use ($order, $lines): Order {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (! $order->status->isOpen()) {
                throw ValidationException::withMessages(['items' => "Đơn {$order->order_number} đã {$order->status->label()}, không thêm món được."]);
            }

            if ($lockReason = $order->amountLockReason()) {
                throw ValidationException::withMessages(['items' => $lockReason]);
            }

            $items = $this->buildOrderItems->handle($lines);
            $this->stock->reserve($items);

            $statusBefore = $order->status->value;

            $order->items()->saveMany($items);
            $order->recalculateTotals();
            $order->syncStatusWithItems();

            // The kitchen must see new items even when the order status stays the same.
            OrderStatusUpdated::send($order, $statusBefore, $order->status->value, OrderStatusUpdated::CHANGE_ITEMS);

            return $order;
        });
    }
}
