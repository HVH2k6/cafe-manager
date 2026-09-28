<?php

namespace App\Http\Middleware;

use App\Models\QrSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer routes that change something (cart, order, call staff) need a
 * session that is still usable; each use pushes its expiry back. An expired
 * session sends the customer back to the table's QR page to start a new one.
 */
class EnsureQrSessionIsUsable
{
    public const MESSAGE = 'Phiên gọi món đã hết hạn. Vui lòng bắt đầu lại.';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var QrSession $session */
        $session = $request->route('qrSession');

        if (! $session->isUsable()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => self::MESSAGE, 'redirect' => route('qr.show', $session->qr_token)], 410);
            }

            return to_route('qr.show', $session->qr_token)->with('error', self::MESSAGE);
        }

        $session->touchActivity();

        return $next($request);
    }
}
