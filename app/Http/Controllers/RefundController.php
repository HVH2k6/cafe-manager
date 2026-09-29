<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RefundPayment;
use App\Events\OrderStatusUpdated;
use App\Models\Payment;
use App\Models\PayosPaymentLink;
use App\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Records refunds made by hand: payOS cannot send money back, so staff give
 * cash or make a bank transfer themselves, then note it here.
 */
class RefundController extends Controller
{
    public function store(Request $request, Payment $payment, RefundPayment $refundPayment): RedirectResponse
    {
        $validated = $request->validateWithBag('refund', [
            'refund_amount' => ['required', 'numeric', 'min:1'],
            'refund_reason' => ['required', 'string', 'max:255'],
            'refund_method' => ['required', Rule::enum(PaymentMethod::class)->only(RefundPayment::methods())],
            'refund_reference' => ['nullable', 'string', 'max:100'],
        ], [
            'refund_amount.required' => 'Nhập số tiền hoàn.',
            'refund_amount.min' => 'Số tiền hoàn phải lớn hơn 0.',
            'refund_reason.required' => 'Ghi lý do hoàn tiền.',
            'refund_method.required' => 'Chọn cách trả lại tiền.',
            'refund_method.enum' => 'Chỉ hoàn bằng tiền mặt hoặc chuyển khoản.',
        ]);

        $orderWasOpen = $payment->order->status->isOpen();

        $refundPayment->handle(
            $payment,
            (float) $validated['refund_amount'],
            $validated['refund_reason'],
            PaymentMethod::from($validated['refund_method']),
            $validated['refund_reference'] ?? null,
            $request->user(),
        );

        $amount = number_format((float) $validated['refund_amount'], 0, ',', '.');

        return back()->with('status', $orderWasOpen
            ? "Đã ghi nhận hoàn {$amount} đ và hủy đơn {$payment->order->order_number}."
            : "Đã ghi nhận hoàn {$amount} đ cho đơn {$payment->order->order_number}.");
    }

    /**
     * A transfer that reached an order already settled another way was given back.
     */
    public function storeForPayosLink(Request $request, PayosPaymentLink $link): RedirectResponse
    {
        if (! $link->needsRefund()) {
            return back()->with('error', 'Khoản chuyển khoản này không cần hoàn hoặc đã hoàn rồi.');
        }

        $validated = $request->validateWithBag('refund', [
            'refund_method' => ['required', Rule::enum(PaymentMethod::class)->only(RefundPayment::methods())],
            'refund_reference' => ['nullable', 'string', 'max:100'],
            'refund_note' => ['nullable', 'string', 'max:255'],
        ], [
            'refund_method.required' => 'Chọn cách trả lại tiền.',
            'refund_method.enum' => 'Chỉ hoàn bằng tiền mặt hoặc chuyển khoản.',
        ]);

        $link->update([...$validated, 'refunded_at' => now(), 'refunded_by' => $request->user()->id]);
        OrderStatusUpdated::changed($link->order, OrderStatusUpdated::CHANGE_PAYMENT);

        return back()->with('status', 'Đã ghi nhận hoàn '.number_format($link->amount_paid, 0, ',', '.').' đ cho khách.');
    }
}
