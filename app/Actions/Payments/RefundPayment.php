<?php

namespace App\Actions\Payments;

use App\Actions\Orders\CancelOrder;
use App\Events\OrderStatusUpdated;
use App\Models\Payment;
use App\Models\User;
use App\OrderStatus;
use App\PaymentMethod;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a refund the staff made by hand — cash back or a bank transfer done
 * outside the app (payOS cannot send money back).
 *
 *  - An order still open is refunded in full and cancelled (e.g. an item ran out):
 *    its held stock is released and the table freed.
 *  - A completed order may be refunded in full or in part (e.g. a complaint)
 *    and stays completed; the goods were already handed out.
 *
 * A payment is refunded once.
 */
class RefundPayment
{
    public function __construct(private CancelOrder $cancelOrder) {}

    /**
     * @throws ValidationException
     */
    public function handle(Payment $payment, float $amount, string $reason, PaymentMethod $method, ?string $reference, User $by): Payment
    {
        return DB::transaction(function () use ($payment, $amount, $reason, $method, $reference, $by): Payment {
            $payment = Payment::with('order')->lockForUpdate()->findOrFail($payment->id);
            $order = $payment->order;
            $paid = (float) $payment->amount;

            if ($payment->isRefunded()) {
                throw ValidationException::withMessages(['refund_amount' => "Khoản thanh toán của đơn {$order->order_number} đã được hoàn tiền trước đó."]);
            }

            if ($amount <= 0 || $amount > $paid) {
                throw ValidationException::withMessages(['refund_amount' => 'Số tiền hoàn phải từ 1 đ đến '.number_format($paid, 0, ',', '.').' đ.']);
            }

            $orderIsOpen = $order->status->isOpen();

            if ($orderIsOpen && $amount < $paid) {
                throw ValidationException::withMessages(['refund_amount' => 'Đơn chưa hoàn thành chỉ hoàn được toàn bộ (và đơn sẽ bị hủy).']);
            }

            if (! $orderIsOpen && $order->status !== OrderStatus::Completed) {
                throw ValidationException::withMessages(['refund_amount' => "Đơn {$order->order_number} đã {$order->status->label()}, không hoàn tiền được."]);
            }

            $payment->update([
                'status' => $amount < $paid ? Payment::STATUS_PARTIALLY_REFUNDED : Payment::STATUS_REFUNDED,
                'refund_amount' => $amount,
                'refund_reason' => $reason,
                'refund_method' => $method,
                'refund_reference' => $reference,
                'refunded_by' => $by->id,
                'refunded_at' => now(),
            ]);

            ActivityLogger::log('payment.refunded', 'Hoàn '.number_format($amount, 0, ',', '.')." đ ({$method->label()}) cho đơn {$order->order_number}: {$reason}", $payment,
                ['amount' => $paid, 'status' => Payment::STATUS_COMPLETED],
                ['refund_amount' => $amount, 'refund_method' => $method->value, 'refund_reference' => $reference, 'refund_reason' => $reason]);

            if ($orderIsOpen) {
                // Cancelling broadcasts the status change (OrderObserver).
                $this->cancelOrder->handle($order, $by, "Hoàn tiền: {$reason}");
            } else {
                // A completed order keeps its status: say its payment changed (order page, reports).
                OrderStatusUpdated::changed($order, OrderStatusUpdated::CHANGE_PAYMENT);
            }

            return $payment;
        });
    }

    /**
     * Refunds are handed back as cash or a manual bank transfer.
     *
     * @return list<PaymentMethod>
     */
    public static function methods(): array
    {
        return [PaymentMethod::Cash, PaymentMethod::BankTransfer];
    }
}
