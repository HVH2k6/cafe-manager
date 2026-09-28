<?php

namespace App\Http\Controllers;

use App\Actions\Qr\CallStaff;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\QrNotification;
use App\OrderItemStatus;
use App\OrderStatus;
use Illuminate\Http\JsonResponse;

/**
 * What the staff pop-ups show, polled when the WebSocket (Reverb) is not
 * connected: customer calls waiting for someone and QR orders to confirm.
 */
class StaffAlertController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            // Every order waiting for confirmation (the sidebar badge), QR or not.
            'pending_count' => Order::where('status', OrderStatus::Pending)->count(),
            'calls' => self::waitingCalls(),
            'pending' => Order::with('diningTable')
                ->where('status', OrderStatus::Pending)
                ->whereNotNull('qr_session_id')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'place' => $order->placeLabel(),
                    'url' => route('orders.show', $order),
                ])
                ->values(),
            'ready' => self::readyToServe(),
        ]);
    }

    /**
     * Open orders with items the kitchen finished that are not taken out yet.
     *
     * @return list<array{id: int, item_ids: list<int>, message: string, url: string}>
     */
    public static function readyToServe(): array
    {
        return Order::with(['diningTable', 'items' => fn ($query) => $query->where('status', OrderItemStatus::Ready)->with(['product', 'bundle'])])
            ->whereIn('status', [OrderStatus::Preparing, OrderStatus::Ready])
            ->whereHas('items', fn ($query) => $query->where('status', OrderItemStatus::Ready))
            ->oldest('id')
            ->limit(20)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'item_ids' => $order->items->pluck('id')->all(),
                'message' => "{$order->placeLabel()} — "
                    .$order->items->map(fn (OrderItem $item): string => "{$item->name()} ×{$item->quantity}")->implode(', ')
                    ." đã xong, mang ra cho khách ({$order->order_number}).",
                'url' => route('orders.show', $order),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, place: string, message: ?string, answer_url: string}>
     */
    public static function waitingCalls(): array
    {
        return CallStaff::waiting()->map(fn (QrNotification $call): array => [
            'id' => $call->id,
            'place' => 'Bàn '.$call->qrSession->diningTable->table_no,
            'message' => $call->message,
            'answer_url' => route('qr-calls.answer', $call),
        ])->values()->all();
    }
}
