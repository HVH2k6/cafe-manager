<?php

namespace App\Http\Controllers;

use App\Actions\Payments\SyncPayosPaymentLink;
use App\Models\PayosPaymentLink;
use App\Services\PayOSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * payOS payment webhook ({ code, desc, success, data, signature }). No login
 * and no CSRF token: the HMAC signature of `data` is what proves it came from payOS.
 */
class PayosWebhookController extends Controller
{
    public function __invoke(Request $request, PayOSService $payos, SyncPayosPaymentLink $sync): JsonResponse
    {
        $data = $request->input('data');

        if (! is_array($data) || ! $payos->verifyWebhookSignature($data, (string) $request->input('signature'))) {
            Log::warning('payOS webhook rejected: invalid signature', ['ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        // Unknown codes include payOS's test webhook sent when the URL is saved: acknowledge it.
        $link = PayosPaymentLink::with('order')->firstWhere('order_code', $data['orderCode'] ?? null);
        $statusBefore = $link?->status;

        if ($link && $request->input('code') === '00') {
            $sync->applyWebhook($link, $data);
        }

        Log::info('payOS webhook received', [
            'order_code' => $data['orderCode'] ?? null,
            'amount' => $data['amount'] ?? null,
            'reference' => $data['reference'] ?? null,
            'result' => match (true) {
                $link === null => 'unknown order code (e.g. payOS test webhook)',
                $statusBefore === PayosPaymentLink::PAID => 'already paid (status check was faster)',
                $link->status === PayosPaymentLink::PAID => 'settled the payment',
                default => "left as {$link->status}",
            },
        ]);

        return response()->json(['success' => true]);
    }
}
