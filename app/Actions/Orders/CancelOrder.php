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
 * Cancels an open order: releases the stock held for it (nothing was taken
 * out yet, so no stock movement is logged) and frees the table.
 */
class CancelOrder
{
    public function __construct(private StockReservation $stock) {}

    /**
     * @throws ValidationException
     */
    public function handle(Order $order, User $by, string $reason): Order
    {
        return DB::transaction(function () use ($order, $by, $reason): Order {
            $order = Order::with('items.bundle.items')->lockForUpdate()->findOrFail($order->id);

            if (! $order->status->isOpen()) {
                throw ValidationException::withMessages(['cancelled_reason' => "Đơn {$order->order_number} đã {$order->status->label()}, không hủy được."]);
            }

            // A paid order is cancelled by refunding it (RefundPayment), which records where the money went.
            if ($order->isPaid()) {
                throw ValidationException::withMessages(['cancelled_reason' => "Đơn {$order->order_number} đã thanh toán. Dùng “Hủy đơn & hoàn tiền” để trả lại tiền cho khách."]);
            }

            // A pending QR could still be paid after cancelling.
            if ($lockReason = $order->amountLockReason()) {
                throw ValidationException::withMessages(['cancelled_reason' => $lockReason]);
            }

            $activeItems = $order->items->where('status', '!=', OrderItemStatus::Cancelled);
            $this->stock->release($activeItems);

            $order->items()
                ->whereIn('status', [OrderItemStatus::Pending, OrderItemStatus::Preparing, OrderItemStatus::Ready])
                ->update(['status' => OrderItemStatus::Cancelled]);

            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_reason' => $reason,
                'cancelled_by' => $by->id,
            ]);

            FreeTable::after($order, TableStatus::Available);

            return $order;
        });
    }
}
