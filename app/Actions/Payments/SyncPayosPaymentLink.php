<?php

namespace App\Actions\Payments;

use App\Actions\Orders\RecordPayment;
use App\Events\OrderStatusUpdated;
use App\Exceptions\PayOSException;
use App\Models\PayosPaymentLink;
use App\Models\User;
use App\PaymentMethod;
use App\Services\PayOSService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Brings a payOS link up to date from payOS (status check or webhook) and,
 * once the transfer is complete, records it as the order's payment.
 *
 * A link counts as paid only when payOS says PAID *and* the amount received
 * covers the link's amount — never because of a query string or the browser.
 */
class SyncPayosPaymentLink
{
    public function __construct(
        private PayOSService $payos,
        private RecordPayment $recordPayment,
    ) {}

    /**
     * Ask payOS for the link's current state.
     *
     * @throws PayOSException
     */
    public function refresh(PayosPaymentLink $link, ?User $by): PayosPaymentLink
    {
        if ($link->isFinal()) {
            return $link;
        }

        return $this->applyPaymentInfo($link, $this->payos->getPaymentInfo($link->order_code), $by);
    }

    /**
     * Apply the `data` of GET /v2/payment-requests/{orderCode}.
     *
     * @param  array<string, mixed>  $info
     */
    public function applyPaymentInfo(PayosPaymentLink $link, array $info, ?User $by): PayosPaymentLink
    {
        $transactions = $info['transactions'] ?? [];

        $link->fill([
            'status' => (string) ($info['status'] ?? $link->status),
            'amount_paid' => (int) ($info['amountPaid'] ?? $link->amount_paid),
            'transactions' => $transactions,
            'reference' => $transactions === [] ? $link->reference : (end($transactions)['reference'] ?? $link->reference),
        ]);

        return $this->settle($link, $info, $by);
    }

    /**
     * Apply the `data` of a verified payment webhook (it reports one incoming transfer).
     *
     * @param  array<string, mixed>  $data
     */
    public function applyWebhook(PayosPaymentLink $link, array $data): PayosPaymentLink
    {
        if (($data['code'] ?? null) !== '00' || $link->status === PayosPaymentLink::PAID) {
            return $link;
        }

        $link->fill([
            'amount_paid' => max($link->amount_paid, (int) ($data['amount'] ?? 0)),
            'transactions' => [...($link->transactions ?? []), $data],
            'reference' => $data['reference'] ?? $link->reference,
        ]);
        $link->status = $link->amount_paid >= $link->amount ? PayosPaymentLink::PAID : $link->status;

        return $this->settle($link, $data, null);
    }

    /**
     * @param  array<string, mixed>  $raw  what payOS sent, kept on the payment for reconciliation
     */
    private function settle(PayosPaymentLink $link, array $raw, ?User $by): PayosPaymentLink
    {
        $isPaid = $link->status === PayosPaymentLink::PAID && $link->amount_paid >= $link->amount;

        if (! $isPaid) {
            // PAID but short (should not happen): keep waiting rather than settle the order.
            if ($link->status === PayosPaymentLink::PAID) {
                $link->status = PayosPaymentLink::PROCESSING;
            }

            $link->save();

            // Expired or cancelled on payOS' side: the order is unlocked, other screens must know.
            if ($link->wasChanged('status') && $link->isFinal()) {
                OrderStatusUpdated::changed($link->order, OrderStatusUpdated::CHANGE_PAYMENT);
            }

            return $link;
        }

        $link->paid_at ??= now();
        $link->save();

        try {
            $this->recordPayment->handle($link->order, PaymentMethod::QrPay, [
                'paid_amount' => $link->amount_paid,
                'transaction_id' => "payos-{$link->order_code}",
                'reference_number' => $link->reference,
                'payment_gateway' => 'payos',
                'gateway_response' => $raw,
            ], $by);
        } catch (ValidationException $exception) {
            // Money arrived for an order already settled another way or closed: staff must refund by hand.
            Log::warning('payOS transfer received for an order that cannot take it', [
                'order_code' => $link->order_code,
                'order_id' => $link->order_id,
                'reason' => collect($exception->errors())->flatten()->first(),
            ]);
        }

        return $link;
    }
}
