<?php

namespace App\Actions\Orders;

use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Settles an order with its one payment (cash, card or a payOS transfer),
 * then completes the order right away if it was already served.
 */
class RecordPayment
{
    public function __construct(private CompleteOrder $completeOrder) {}

    /**
     * @param  array{paid_amount?: int|float|string, card_last4?: ?string, transaction_id?: ?string, reference_number?: ?string, payment_gateway?: ?string, gateway_response?: ?array<string, mixed>, notes?: ?string}  $details
     *
     * @throws ValidationException
     */
    public function handle(Order $order, PaymentMethod $method, array $details, ?User $by): Payment
    {
        $payment = DB::transaction(function () use ($order, $method, $details, $by): Payment {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $existing = $order->payment;

            // The same transfer reported twice (status check + webhook) is recorded once.
            if ($existing && filled($details['transaction_id'] ?? null) && $existing->transaction_id === $details['transaction_id']) {
                return $existing;
            }

            if ($existing) {
                throw ValidationException::withMessages(['payment' => "Đơn {$order->order_number} đã được thanh toán ({$existing->method->label()})."]);
            }

            if (! $order->status->isOpen()) {
                throw ValidationException::withMessages(['payment' => "Đơn {$order->order_number} đã {$order->status->label()}, không thu tiền được."]);
            }

            $total = (float) $order->total_amount;
            $paidAmount = (float) ($details['paid_amount'] ?? $total);

            if ($paidAmount < $total) {
                throw ValidationException::withMessages(['paid_amount' => 'Số tiền nhận chưa đủ '.number_format($total, 0, ',', '.').' đ.']);
            }

            return $order->payment()->create([
                'amount' => $total,
                'method' => $method,
                'status' => Payment::STATUS_COMPLETED,
                'paid_amount' => $paidAmount,
                'change_amount' => $method === PaymentMethod::Cash ? $paidAmount - $total : 0,
                'card_last4' => $details['card_last4'] ?? null,
                'transaction_id' => $details['transaction_id'] ?? null,
                'reference_number' => $details['reference_number'] ?? null,
                'payment_gateway' => $details['payment_gateway'] ?? null,
                'gateway_response' => $details['gateway_response'] ?? null,
                'notes' => $details['notes'] ?? null,
                'processed_by' => $by?->id,
                'paid_at' => now(),
            ]);
        });

        $order = $order->fresh();
        OrderStatusUpdated::send($order, $order->status->value, $order->status->value, OrderStatusUpdated::CHANGE_PAYMENT);

        $this->completeOrder->ifSettled($order, $by);

        return $payment;
    }
}
