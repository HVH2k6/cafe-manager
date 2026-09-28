<?php

namespace App\Http\Controllers;

use App\Actions\Orders\RecordPayment;
use App\Models\Order;
use App\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * Take payment at the counter: cash (with change) or card on the bank's terminal.
     * payOS transfers go through PayosPaymentController instead.
     */
    public function store(Request $request, Order $order, RecordPayment $recordPayment): RedirectResponse
    {
        $validated = $request->validateWithBag('payment', [
            'method' => ['required', Rule::enum(PaymentMethod::class)->only([PaymentMethod::Cash, PaymentMethod::Card])],
            'paid_amount' => ['nullable', 'required_if:method,cash', 'numeric', 'min:0', 'max:999999999'],
            'card_last4' => ['nullable', 'digits:4'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'method.required' => 'Chọn hình thức thanh toán.',
            'paid_amount.required_if' => 'Nhập số tiền khách đưa.',
            'paid_amount.numeric' => 'Số tiền phải là số.',
            'card_last4.digits' => '4 số cuối thẻ phải gồm đúng 4 chữ số.',
        ]);

        if ($order->payosLinks()->awaitingPayment()->exists()) {
            return back()->with('error', 'Đơn đang có mã QR chờ chuyển khoản. Hãy hủy mã QR trước khi thu tiền cách khác.');
        }

        $method = PaymentMethod::from($validated['method']);

        $payment = $recordPayment->handle($order, $method, [
            'paid_amount' => $method === PaymentMethod::Cash ? $validated['paid_amount'] : $order->total_amount,
            'card_last4' => $validated['card_last4'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ], $request->user());

        $change = (float) $payment->change_amount > 0 ? ' Tiền thừa: '.number_format((float) $payment->change_amount, 0, ',', '.').' đ.' : '';

        return back()->with('status', "Đã thu {$method->label()} cho đơn {$order->order_number}.{$change}");
    }
}
