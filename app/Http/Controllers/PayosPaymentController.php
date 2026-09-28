<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CreatePayosPaymentLink;
use App\Actions\Payments\SyncPayosPaymentLink;
use App\Exceptions\PayOSException;
use App\Models\Order;
use App\Models\PayosPaymentLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * payOS bank transfer shown as a VietQR code on the order page. The browser
 * only talks to these routes; payOS is called from the server with the keys.
 */
class PayosPaymentController extends Controller
{
    public function __construct(private CreatePayosPaymentLink $links) {}

    /**
     * Create a payment link for the order's current total, replacing any link still waiting.
     */
    public function store(Request $request, Order $order): RedirectResponse
    {
        try {
            $link = $this->links->handle($order, $request->user()->id, fn (int $orderCode): string => route('payos.return', ['link' => $orderCode]));
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first());
        } catch (PayOSException $exception) {
            return back()->with('error', "payOS: {$exception->getMessage()}");
        }

        return to_route('orders.show', $order)->withFragment('payment')
            ->with('status', 'Đã tạo mã QR '.number_format($link->amount, 0, ',', '.').' đ. Mời khách quét để chuyển khoản.');
    }

    /**
     * Polled by the order page every few seconds while the QR code is shown.
     */
    public function status(Request $request, PayosPaymentLink $link, SyncPayosPaymentLink $sync): JsonResponse
    {
        $error = null;

        try {
            $link = $sync->refresh($link, $request->user());
        } catch (PayOSException $exception) {
            $error = $exception->getMessage();
        }

        return response()->json([
            'status' => $link->status,
            'label' => $link->statusLabel(),
            'final' => $link->isFinal(),
            'orderPaid' => $link->order->isPaid(),
            'error' => $error,
        ]);
    }

    public function cancel(Request $request, PayosPaymentLink $link, SyncPayosPaymentLink $sync): RedirectResponse
    {
        try {
            $this->links->cancelLink($link, 'Nhân viên hủy mã QR');
            $link = $sync->refresh($link, $request->user());
        } catch (PayOSException $exception) {
            return back()->with('error', "payOS: {$exception->getMessage()}");
        }

        return to_route('orders.show', $link->order_id)->with('status', $link->status === PayosPaymentLink::PAID
            ? 'Khách đã chuyển khoản trước khi hủy — đơn đã được ghi nhận thanh toán.'
            : 'Đã hủy mã QR.');
    }

    /**
     * payOS returnUrl and cancelUrl. The query string (?status=PAID…) is ignored:
     * the link's state is always read again from payOS.
     */
    public function returned(Request $request, PayosPaymentLink $link, SyncPayosPaymentLink $sync): RedirectResponse
    {
        try {
            $link = $sync->refresh($link, $request->user());
        } catch (PayOSException $exception) {
            return to_route('orders.show', $link->order_id)->with('error', "Chưa kiểm tra được thanh toán: {$exception->getMessage()}");
        }

        return to_route('orders.show', $link->order_id)->with(
            $link->status === PayosPaymentLink::PAID ? 'status' : 'error',
            $link->statusLabel().'.',
        );
    }
}
