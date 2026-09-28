<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CompleteOrder;
use App\Models\Order;
use App\OrderItemStatus;
use App\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderStatusController extends Controller
{
    /**
     * Accept an order customers sent themselves (QR, step 9) so the kitchen sees it.
     */
    public function confirm(Order $order): RedirectResponse
    {
        if ($order->status !== OrderStatus::Pending) {
            return back()->with('error', "Đơn {$order->order_number} không ở trạng thái chờ xác nhận.");
        }

        $order->update(['status' => OrderStatus::Confirmed]);

        return back()->with('status', "Đã xác nhận đơn {$order->order_number}.");
    }

    /**
     * The waiter took every finished item to the customer.
     */
    /**
     * The waiter took every finished item to the customer. An order that was
     * already paid (e.g. takeaway paid upfront) is completed right away.
     */
    public function serve(Request $request, Order $order, CompleteOrder $completeOrder): RedirectResponse
    {
        $readyItems = $order->items()->where('status', OrderItemStatus::Ready);

        if (! $readyItems->exists()) {
            return back()->with('error', 'Chưa có món nào làm xong để mang ra.');
        }

        DB::transaction(function () use ($order, $readyItems): void {
            $readyItems->update(['status' => OrderItemStatus::Served, 'served_at' => now()]);
            $order->syncStatusWithItems();
        });

        if ($completeOrder->ifSettled($order, $request->user())) {
            return back()->with('status', "Đã mang ra đủ món. Đơn {$order->order_number} đã thanh toán nên đã hoàn thành.");
        }

        return back()->with('status', $order->status === OrderStatus::Served
            ? "Đã mang ra đủ món cho đơn {$order->order_number}. Chờ khách thanh toán."
            : 'Đã mang ra các món làm xong; vẫn còn món đang làm.');
    }

    public function cancel(Request $request, Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        $validated = $request->validateWithBag('cancel', [
            'cancelled_reason' => ['required', 'string', 'max:255'],
        ], [
            'cancelled_reason.required' => 'Vui lòng ghi lý do hủy đơn.',
            'cancelled_reason.max' => 'Lý do tối đa 255 ký tự.',
        ]);

        $order = $cancelOrder->handle($order, $request->user(), $validated['cancelled_reason']);

        return back()->with('status', "Đã hủy đơn {$order->order_number}.");
    }
}
