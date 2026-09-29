<?php

namespace App\Http\Controllers;

use App\Actions\Orders\AddOrderItems;
use App\Actions\Orders\StockReservation;
use App\Events\OrderStatusUpdated;
use App\Http\Requests\OrderLinesRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\OrderItemStatus;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class OrderItemController extends Controller
{
    public function store(OrderLinesRequest $request, Order $order, AddOrderItems $addOrderItems): RedirectResponse
    {
        $order = $addOrderItems->handle($order, $request->lines());

        return to_route('orders.show', $order)->with('status', 'Đã thêm '.count($request->lines())." dòng món vào đơn {$order->order_number}.");
    }

    /**
     * Take a line off the order before the kitchen starts it.
     */
    public function destroy(Order $order, OrderItem $item, StockReservation $stock): RedirectResponse
    {
        if ($item->status !== OrderItemStatus::Pending || ! $order->status->isOpen()) {
            return back()->with('error', "“{$item->name()}” đã được bếp làm hoặc đơn đã đóng, không bỏ món được.");
        }

        if ($lockReason = $order->amountLockReason()) {
            return back()->with('error', $lockReason);
        }

        DB::transaction(function () use ($order, $item, $stock): void {
            $statusBefore = $order->status->value;

            $item->load('bundle.items');
            $stock->release([$item]);
            $item->update(['status' => OrderItemStatus::Cancelled]);

            $order->recalculateTotals();
            $order->syncStatusWithItems();

            ActivityLogger::log('order.item_removed', "Bỏ “{$item->name()}” ×{$item->quantity} khỏi đơn {$order->order_number}", $order,
                ['item' => $item->name(), 'quantity' => $item->quantity, 'line_total' => $item->lineTotal()], ['total_amount' => (float) $order->total_amount]);

            OrderStatusUpdated::send($order, $statusBefore, $order->status->value, OrderStatusUpdated::CHANGE_ITEMS);
        });

        return back()->with('status', "Đã bỏ “{$item->name()}” khỏi đơn.");
    }
}
