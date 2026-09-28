<?php

namespace App\Http\Controllers\Qr;

use App\Actions\Orders\LoadMenu;
use App\Actions\Qr\CreateQrPaymentLink;
use App\Actions\Qr\SubmitQrOrder;
use App\Exceptions\PayOSException;
use App\Http\Controllers\Controller;
use App\Http\Requests\QrCartRequest;
use App\Http\Requests\SubmitQrOrderRequest;
use App\Models\QrOperatingHour;
use App\Models\QrSession;
use App\PaymentMethod;
use App\Services\PayOSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The customer's menu, cart and checkout (one page driven by Alpine).
 */
class QrMenuController extends Controller
{
    public function show(QrSession $qrSession, LoadMenu $loadMenu, PayOSService $payos): View
    {
        $qrSession->load(['diningTable', 'order']);

        $paymentMethods = collect(SubmitQrOrderRequest::PAYMENT_METHODS)
            // Transfers and wallets are paid with the payOS VietQR code, so they need payOS.
            ->filter(fn (PaymentMethod $method): bool => in_array($method, [PaymentMethod::Cash, PaymentMethod::Card], true) || $payos->isConfigured())
            ->values();

        return view('qr.menu', [
            'session' => $qrSession,
            'table' => $qrSession->diningTable,
            'menu' => $loadMenu->handle(),
            'cart' => [
                'items' => $qrSession->cart_data['items'] ?? [],
                'customerName' => $qrSession->cart_data['customer_name'] ?? null,
            ],
            'openOrder' => $qrSession->order?->status->isOpen() ? $qrSession->order : null,
            'isOpen' => QrOperatingHour::isOpenAt(now()),
            'paymentMethods' => $paymentMethods,
        ]);
    }

    /**
     * Keep the cart on the server so a reload or a closed tab does not lose it.
     */
    public function updateCart(QrCartRequest $request, QrSession $qrSession): Response
    {
        $qrSession->update(['cart_data' => [
            'customer_name' => filled($request->validated('customer_name')) ? trim($request->validated('customer_name')) : ($qrSession->cart_data['customer_name'] ?? null),
            'items' => $request->lines(),
        ]]);

        return response()->noContent();
    }

    public function store(SubmitQrOrderRequest $request, QrSession $qrSession, SubmitQrOrder $submitQrOrder, CreateQrPaymentLink $createLink, PayOSService $payos): JsonResponse
    {
        $order = $submitQrOrder->handle($qrSession, $request->lines(), $request->details());

        // Transfer / e-wallet: show the VietQR code on the order page straight away.
        $paysOnline = in_array(PaymentMethod::from($request->validated('payment_method')), [PaymentMethod::BankTransfer, PaymentMethod::EWallet], true);

        if ($paysOnline && $payos->isConfigured() && ! $order->isPaid() && ! $order->payosLinks()->awaitingPayment()->exists()) {
            try {
                $createLink->handle($qrSession->fresh());
            } catch (PayOSException|ValidationException $exception) {
                // The order is sent anyway; the order page offers "Chuyển khoản ngay" to try again.
                report($exception);
            }
        }

        $request->session()->flash('qr_placed', true);

        return response()->json(['redirect' => route('qr.order', $qrSession)], 201);
    }
}
