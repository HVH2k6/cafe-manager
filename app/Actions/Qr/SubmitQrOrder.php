<?php

namespace App\Actions\Qr;

use App\Actions\Orders\AddOrderItems;
use App\Actions\Orders\PlaceOrder;
use App\Models\Order;
use App\Models\QrNotification;
use App\Models\QrOperatingHour;
use App\Models\QrSession;
use App\OrderStatus;
use App\QrNotificationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a customer's cart into an order. The table's first order waits for
 * staff to confirm it (status pending); when the table already has an open
 * order — the same phone ordering more, or friends at the same table — the
 * items are added to that order instead. Prices always come from the menu.
 */
class SubmitQrOrder
{
    public function __construct(
        private PlaceOrder $placeOrder,
        private AddOrderItems $addOrderItems,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array{customer_name?: ?string, notes?: ?string, payment_preference?: ?string}  $details
     *
     * @throws ValidationException
     */
    public function handle(QrSession $session, array $lines, array $details): Order
    {
        $this->ensureCanOrder($session);

        $order = $this->openOrderOfTable($session);

        if (! $order) {
            try {
                $order = $this->placeOrder->handle(null, [
                    'order_type' => 'dine_in',
                    'table_id' => $session->table_id,
                    'customer_name' => $details['customer_name'] ?? null,
                    'notes' => $details['notes'] ?? null,
                    'payment_preference' => $details['payment_preference'] ?? null,
                    'qr_session_id' => $session->id,
                ], $lines, OrderStatus::Pending);
            } catch (ValidationException $exception) {
                // Another phone at the table sent its order a moment earlier: join that order.
                $order = array_key_exists('table_id', $exception->errors()) ? $this->openOrderOfTable($session) : null;

                if (! $order) {
                    throw $exception;
                }
            }
        }

        if (! $order->wasRecentlyCreated) {
            $order = $this->addTo($order, $lines);
        }

        DB::transaction(function () use ($session, $order): void {
            $session->forceFill([
                'order_id' => $order->id,
                'cart_data' => ['customer_name' => $session->cart_data['customer_name'] ?? null, 'items' => []],
                'last_activity' => now(),
                'expires_at' => now()->addMinutes(QrSession::LIFETIME_MINUTES),
            ])->save();

            QrNotification::create([
                'qr_session_id' => $session->id,
                'order_id' => $order->id,
                'type' => QrNotificationType::OrderReceived,
                'title' => 'Đã gửi đơn',
                'message' => "Đơn {$order->order_number} đã được gửi tới quầy.",
            ]);
        });

        return $order;
    }

    /**
     * @throws ValidationException
     */
    public function ensureCanOrder(QrSession $session): void
    {
        $session->loadMissing(['qrCode', 'diningTable']);

        if (! $session->isUsable()) {
            $this->fail('Phiên gọi món đã hết hạn. Vui lòng quét lại mã QR trên bàn.');
        }

        if (! $session->qrCode?->is_active) {
            $this->fail('Mã QR của bàn này đang tạm ngưng. Vui lòng gọi nhân viên.');
        }

        if (! QrOperatingHour::isOpenAt(now())) {
            $this->fail('Quán đang ngoài giờ nhận đơn qua QR. Vui lòng gọi nhân viên.');
        }
    }

    private function openOrderOfTable(QrSession $session): ?Order
    {
        return Order::open()->where('table_id', $session->table_id)->latest('id')->first();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     *
     * @throws ValidationException
     */
    private function addTo(Order $order, array $lines): Order
    {
        if ($order->isPaid()) {
            $this->fail("Bàn đang có đơn {$order->order_number} đã thanh toán. Vui lòng gọi nhân viên để gọi thêm món.");
        }

        if ($order->amountLockReason()) {
            $this->fail("Đơn {$order->order_number} của bàn đang chờ chuyển khoản. Hãy thanh toán xong hoặc gọi nhân viên để gọi thêm món.");
        }

        return $this->addOrderItems->handle($order, $lines);
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['order' => $message]);
    }
}
