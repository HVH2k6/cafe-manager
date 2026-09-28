<?php

namespace App\Http\Controllers\Qr;

use App\Actions\Payments\SyncPayosPaymentLink;
use App\Actions\Qr\CallStaff;
use App\Actions\Qr\CreateQrPaymentLink;
use App\Exceptions\PayOSException;
use App\Http\Controllers\Controller;
use App\Models\PayosPaymentLink;
use App\Models\QrSession;
use App\QrNotificationType;
use App\Services\PayOSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The customer follows their order (live), calls staff and pays by VietQR.
 */
class QrOrderController extends Controller
{
    public function show(Request $request, QrSession $qrSession, PayOSService $payos): View|RedirectResponse
    {
        $order = $qrSession->order;

        if (! $order) {
            return to_route('qr.menu', $qrSession);
        }

        $order->load(['diningTable', 'items.product', 'items.bundle.items.product', 'payment']);
        $payosLink = $order->payosLinks()->first();
        $waitingLink = $payosLink?->isAwaitingPayment() && filled($payosLink->bin) && filled($payosLink->account_number) ? $payosLink : null;

        return view('qr.order', [
            'session' => $qrSession,
            'order' => $order,
            'justPlaced' => (bool) $request->session()->get('qr_placed'),
            'waitingLink' => $waitingLink,
            'qrImage' => $waitingLink ? $payos->buildQrImageUrl($waitingLink->bin, $waitingLink->account_number, $waitingLink->amount, $waitingLink->description) : null,
            'canPayOnline' => $payos->isConfigured() && $this->payableOnline($qrSession) === null,
            'payOnlineHint' => $payos->isConfigured() ? $this->payableOnline($qrSession) : null,
            'lastCall' => $qrSession->notifications()->where('type', QrNotificationType::CallStaff)->where('created_at', '>=', now()->subMinutes(15))->first(),
            'canOrderMore' => $qrSession->isUsable() && $order->status->isOpen(),
        ]);
    }

    public function callStaff(QrSession $qrSession, CallStaff $callStaff): RedirectResponse
    {
        $callStaff->handle($qrSession);

        return back()->with('status', 'Đã gọi nhân viên, vui lòng chờ trong giây lát.');
    }

    /**
     * Create (or replace an expired) VietQR code for the order's current total.
     */
    public function pay(QrSession $qrSession, CreateQrPaymentLink $createLink): RedirectResponse
    {
        if ($reason = $this->payableOnline($qrSession)) {
            return back()->with('error', $reason);
        }

        try {
            $createLink->handle($qrSession);
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first());
        } catch (PayOSException) {
            return back()->with('error', 'Chưa tạo được mã chuyển khoản, vui lòng thử lại hoặc gọi nhân viên.');
        }

        return to_route('qr.order', $qrSession)->withFragment('payment');
    }

    /**
     * Polled by the customer's page while the VietQR code is shown.
     */
    public function payStatus(QrSession $qrSession, SyncPayosPaymentLink $sync): JsonResponse
    {
        $link = $this->latestLink($qrSession);
        $error = null;

        try {
            $link = $sync->refresh($link, null);
        } catch (PayOSException) {
            $error = 'Đang kiểm tra lại…';
        }

        return response()->json([
            'status' => $link->status,
            'label' => $link->statusLabel(),
            'final' => $link->isFinal(),
            'orderPaid' => $link->order->isPaid(),
            'error' => $error,
        ]);
    }

    /**
     * payOS return and cancel page: the query string is ignored, the state is asked again from payOS.
     */
    public function payReturn(QrSession $qrSession, SyncPayosPaymentLink $sync): RedirectResponse
    {
        $link = $this->latestLink($qrSession);

        try {
            $link = $sync->refresh($link, null);
        } catch (PayOSException) {
            return to_route('qr.order', $qrSession)->with('error', 'Chưa kiểm tra được thanh toán, trang sẽ tự cập nhật.');
        }

        return to_route('qr.order', $qrSession)->with(
            $link->status === PayosPaymentLink::PAID ? 'status' : 'error',
            $link->status === PayosPaymentLink::PAID ? 'Đã nhận thanh toán. Cảm ơn bạn!' : $link->statusLabel().'.',
        );
    }

    /**
     * Why the customer cannot pay online right now, or null if they can.
     */
    private function payableOnline(QrSession $qrSession): ?string
    {
        $order = $qrSession->order;

        return match (true) {
            ! $order || ! $order->status->isOpen() => 'Đơn đã đóng.',
            $order->isPaid() => 'Đơn đã được thanh toán.',
            // Paying before staff confirm is allowed: if the bar cannot serve the order, staff refund it by hand (step 7).
            default => null,
        };
    }

    private function latestLink(QrSession $qrSession): PayosPaymentLink
    {
        abort_unless($qrSession->order, 404);

        return $qrSession->order->payosLinks()->firstOrFail();
    }
}
