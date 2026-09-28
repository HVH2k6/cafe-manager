<?php

namespace App\Actions\Qr;

use App\Actions\Payments\CreatePayosPaymentLink;
use App\Exceptions\PayOSException;
use App\Models\PayosPaymentLink;
use App\Models\QrNotification;
use App\Models\QrSession;
use App\QrNotificationType;
use Illuminate\Validation\ValidationException;

/**
 * VietQR code (payOS) for the order a customer follows, so they pay from
 * their phone. Created right after a transfer / e-wallet order is sent, or
 * when the customer presses "Chuyển khoản ngay".
 */
class CreateQrPaymentLink
{
    public function __construct(private CreatePayosPaymentLink $links) {}

    /**
     * @throws PayOSException
     * @throws ValidationException
     */
    public function handle(QrSession $session): PayosPaymentLink
    {
        $order = $session->order;

        if (! $order) {
            throw ValidationException::withMessages(['payment' => 'Chưa có đơn để thanh toán.']);
        }

        $link = $this->links->handle($order, null, fn (): string => route('qr.pay.return', $session));

        QrNotification::create([
            'qr_session_id' => $session->id,
            'order_id' => $order->id,
            'type' => QrNotificationType::PaymentLink,
            'title' => 'Mã chuyển khoản',
            'message' => 'Mã QR '.number_format($link->amount, 0, ',', '.').' đ cho đơn '.$order->order_number.'.',
            'action_url' => $link->checkout_url,
        ]);

        return $link;
    }
}
