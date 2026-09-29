<?php

namespace App\Actions\Payments;

use App\Events\OrderStatusUpdated;
use App\Exceptions\PayOSException;
use App\Models\Order;
use App\Models\PayosPaymentLink;
use App\Services\PayOSService;
use Illuminate\Validation\ValidationException;

/**
 * Creates a payOS payment link (VietQR) for the order's current total,
 * cancelling any link still waiting. Used from the staff order page and from
 * the customer's QR order page. The amount always comes from the order.
 */
class CreatePayosPaymentLink
{
    private const CODE_ATTEMPTS = 3;

    public function __construct(private PayOSService $payos) {}

    /**
     * @param  callable(int $orderCode): string  $returnUrl  where payOS sends the payer back (return and cancel)
     *
     * @throws PayOSException
     * @throws ValidationException
     */
    public function handle(Order $order, ?int $createdBy, callable $returnUrl): PayosPaymentLink
    {
        if (! $order->status->isOpen() || $order->isPaid()) {
            throw ValidationException::withMessages(['payment' => "Đơn {$order->order_number} đã thanh toán hoặc đã đóng."]);
        }

        $amount = (int) round((float) $order->total_amount);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['payment' => 'Đơn không có tiền cần thu.']);
        }

        $this->cancelWaitingLinks($order, 'Tạo mã QR mới');

        return $this->createLink($order, $amount, $createdBy, $returnUrl);
    }

    /**
     * @throws PayOSException
     */
    public function cancelWaitingLinks(Order $order, string $reason): void
    {
        foreach ($order->payosLinks()->awaitingPayment()->get() as $link) {
            $this->cancelLink($link, $reason);
        }
    }

    /**
     * @throws PayOSException
     */
    public function cancelLink(PayosPaymentLink $link, string $reason): void
    {
        if ($link->isFinal()) {
            return;
        }

        $data = $this->payos->cancelPaymentLink($link->order_code, $reason);

        $link->update(['status' => $data['status'] ?? PayosPaymentLink::CANCELLED]);

        // The order is no longer locked by a waiting QR code.
        OrderStatusUpdated::changed($link->order, OrderStatusUpdated::CHANGE_PAYMENT);
    }

    /**
     * @param  callable(int $orderCode): string  $returnUrl
     *
     * @throws PayOSException
     */
    private function createLink(Order $order, int $amount, ?int $createdBy, callable $returnUrl): PayosPaymentLink
    {
        $description = PayosPaymentLink::descriptionFor($order);
        $expiresAt = now()->addMinutes(config('services.payos.link_lifetime_minutes'));

        for ($attempt = 1; ; $attempt++) {
            $orderCode = PayosPaymentLink::newOrderCode();

            $link = $order->payosLinks()->make([
                'order_code' => $orderCode,
                'amount' => $amount,
                'description' => $description,
                'status' => PayosPaymentLink::PENDING,
                'expires_at' => $expiresAt,
                'created_by' => $createdBy,
            ]);

            try {
                $data = $this->payos->createPaymentLink(
                    $orderCode,
                    $amount,
                    $description,
                    $returnUrl($orderCode),
                    $returnUrl($orderCode),
                    $expiresAt->getTimestamp(),
                );
            } catch (PayOSException $exception) {
                if ($exception->isDuplicateOrderCode() && $attempt < self::CODE_ATTEMPTS) {
                    continue;
                }

                throw $exception;
            }

            $link->fill([
                'payment_link_id' => $data['paymentLinkId'] ?? null,
                'bin' => $data['bin'] ?? null,
                'account_number' => $data['accountNumber'] ?? null,
                'account_name' => $data['accountName'] ?? null,
                'checkout_url' => $data['checkoutUrl'] ?? null,
                'qr_code' => $data['qrCode'] ?? null,
                'status' => $data['status'] ?? PayosPaymentLink::PENDING,
            ])->save();

            // A waiting QR code locks the order's items and amount: other screens must show it.
            OrderStatusUpdated::changed($order, OrderStatusUpdated::CHANGE_PAYMENT);

            return $link;
        }
    }
}
