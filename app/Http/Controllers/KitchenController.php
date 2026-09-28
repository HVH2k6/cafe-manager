<?php

namespace App\Http\Controllers;

use App\Events\OrderItemReady;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderItem;
use App\OrderItemStatus;
use App\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KitchenController extends Controller
{
    /**
     * Confirmed orders with items still to make, oldest first. Unconfirmed
     * (pending) orders stay off the board until staff accept them.
     */
    public function index(): View
    {
        $orders = Order::with([
            'diningTable',
            'items' => fn ($query) => $query->whereIn('status', [OrderItemStatus::Pending, OrderItemStatus::Preparing, OrderItemStatus::Ready])->with(['product', 'bundle.items.product']),
        ])
            ->whereIn('status', [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready])
            ->whereHas('items', fn (Builder $query) => $query->whereIn('status', [OrderItemStatus::Pending, OrderItemStatus::Preparing]))
            ->oldest('id')
            ->get();

        return view('kitchen.index', [
            'orders' => $orders,
            'counts' => [
                'pending' => $orders->sum(fn (Order $order): int => $order->items->where('status', OrderItemStatus::Pending)->count()),
                'preparing' => $orders->sum(fn (Order $order): int => $order->items->where('status', OrderItemStatus::Preparing)->count()),
            ],
        ]);
    }

    /**
     * Start an item or mark it done; the order's status follows its items.
     */
    public function update(Request $request, OrderItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderItemStatus::class)->only([OrderItemStatus::Preparing, OrderItemStatus::Ready])],
        ]);
        $target = OrderItemStatus::from($validated['status']);

        $allowedFrom = $target === OrderItemStatus::Preparing
            ? [OrderItemStatus::Pending]
            : [OrderItemStatus::Pending, OrderItemStatus::Preparing];

        if (! in_array($item->status, $allowedFrom, true)
            || ! in_array($item->order->status, [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready], true)) {
            return back()->with('error', "“{$item->name()}” đã được cập nhật hoặc đơn đã đổi trạng thái. Trang đã được tải lại.");
        }

        DB::transaction(function () use ($request, $item, $target): void {
            $item->update([
                'status' => $target,
                'prepared_by' => $request->user()->id,
                'prepared_at' => $target === OrderItemStatus::Ready ? now() : $item->prepared_at,
            ]);

            $order = $item->order;
            $statusBefore = $order->status;
            $order->syncStatusWithItems();

            // A status change is broadcast by OrderObserver (with OrderReady for the last item).
            // Otherwise the order pages still need to know an item moved.
            if ($order->status === $statusBefore) {
                OrderStatusUpdated::send($order, $order->status->value, $order->status->value, OrderStatusUpdated::CHANGE_ITEMS);
            }

            if ($target === OrderItemStatus::Ready && $order->status !== OrderStatus::Ready) {
                OrderItemReady::send($order, $item);
            }
        });

        return back();
    }
}
