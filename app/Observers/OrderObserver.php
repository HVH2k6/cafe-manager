<?php

namespace App\Observers;

use App\Events\OrderCompleted;
use App\Events\OrderReady;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\QrNotification;
use App\Models\QrSession;
use App\OrderStatus;
use App\QrNotificationType;
use App\QrSessionStatus;

/**
 * Order statuses are always changed through the model (confirm, kitchen,
 * serve, cancel, complete…), so one observer broadcasts every status change.
 * Broadcasting itself waits for the transaction to commit (OrderBroadcast::send).
 *
 * For orders customers follow by QR it also keeps their session history and
 * closes their sessions once the order is completed or cancelled.
 */
class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $previous = $order->getPrevious()['status'] ?? null;
        $oldStatus = $previous instanceof OrderStatus ? $previous->value : (string) $previous;

        OrderStatusUpdated::send($order, $oldStatus, $order->status->value);

        match ($order->status) {
            OrderStatus::Ready => OrderReady::send($order),
            OrderStatus::Completed => OrderCompleted::send($order),
            default => null,
        };

        $this->updateQrSessions($order);
    }

    private function updateQrSessions(Order $order): void
    {
        $sessions = QrSession::where('order_id', $order->id)->get();

        if ($sessions->isEmpty()) {
            return;
        }

        [$type, $title] = match ($order->status) {
            OrderStatus::Confirmed => [QrNotificationType::OrderReceived, 'Quầy đã xác nhận đơn'],
            OrderStatus::Preparing => [QrNotificationType::OrderPreparing, 'Đang chuẩn bị món'],
            OrderStatus::Ready => [QrNotificationType::OrderReady, 'Món đã sẵn sàng'],
            default => [null, null],
        };

        foreach ($sessions as $session) {
            if ($type) {
                QrNotification::create([
                    'qr_session_id' => $session->id,
                    'order_id' => $order->id,
                    'type' => $type,
                    'title' => $title,
                    'message' => "Đơn {$order->order_number}: {$order->status->label()}.",
                ]);
            }

            if (! $order->status->isOpen() && $session->status === QrSessionStatus::Active) {
                $session->update(['status' => QrSessionStatus::Completed, 'completed_at' => now()]);
            }
        }
    }
}
