<?php

namespace App\Http\Controllers\Qr;

use App\Actions\Qr\CallStaff;
use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Models\QrOperatingHour;
use App\Models\QrSession;
use App\Models\Setting;
use App\QrNotificationType;
use App\TableStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The page a table's QR code opens (no login): which table, whether the cafe
 * takes QR orders right now, a session to order or call staff with, and what
 * this phone ordered recently.
 */
class QrSessionController extends Controller
{
    private const HISTORY_SESSIONS = 10;

    public function show(Request $request, QrCode $qrCode): View
    {
        $table = $qrCode->diningTable;

        if ($unavailable = $this->unavailableReason($qrCode)) {
            return $this->unavailable($unavailable, $table->table_no);
        }

        $session = $this->currentSession($request, $qrCode);

        if ($session) {
            $this->remember($request, $qrCode, $session);
        }

        return view('qr.landing', [
            'qrCode' => $qrCode,
            'table' => $table,
            'session' => $session,
            'openOrder' => $session?->order?->status->isOpen() ? $session->order : null,
            'history' => $this->history($request, $qrCode),
            'historyMinutes' => (int) Setting::get('qr_history_minutes'),
            'lastCall' => $session?->notifications()->where('type', QrNotificationType::CallStaff)->where('created_at', '>=', now()->subMinutes(15))->first(),
            'isOpen' => QrOperatingHour::isOpenAt(now()),
            'hoursToday' => QrOperatingHour::labelFor(now()),
        ]);
    }

    /**
     * Start (or continue) the customer's session, then open the menu or call staff.
     */
    public function store(Request $request, QrCode $qrCode, CallStaff $callStaff): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:100'],
            'intent' => ['required', 'in:menu,call'],
        ], [
            'customer_name.max' => 'Tên tối đa 100 ký tự.',
        ]);

        if ($unavailable = $this->unavailableReason($qrCode)) {
            return back()->with('error', $unavailable);
        }

        $session = $this->currentSession($request, $qrCode) ?? QrSession::start($qrCode, $request);
        $request->session()->put($this->sessionKey($qrCode), $session->session_token);
        $this->remember($request, $qrCode, $session);

        if (filled($validated['customer_name'] ?? null)) {
            $session->update(['cart_data' => [...($session->cart_data ?? []), 'customer_name' => trim($validated['customer_name'])]]);
        }

        if ($validated['intent'] === 'call') {
            $callStaff->handle($session);

            return to_route('qr.show', $qrCode->qr_token)->with('status', 'Đã gọi nhân viên, vui lòng chờ trong giây lát.');
        }

        return to_route('qr.menu', $session);
    }

    /**
     * The session this phone already started for the code, if it can still be used.
     */
    private function currentSession(Request $request, QrCode $qrCode): ?QrSession
    {
        $token = $request->session()->get($this->sessionKey($qrCode));

        if (! $token) {
            return null;
        }

        $session = QrSession::with('order')->where('session_token', $token)->where('qr_token', $qrCode->qr_token)->first();

        return $session?->isUsable() ? $session : null;
    }

    private function sessionKey(QrCode $qrCode): string
    {
        return "qr_sessions.{$qrCode->id}";
    }

    /**
     * Every session this phone started at the table, so its orders stay
     * listed for a while after they end (kept in the browser session only).
     */
    private function historyKey(QrCode $qrCode): string
    {
        return "qr_history.{$qrCode->id}";
    }

    private function remember(Request $request, QrCode $qrCode, QrSession $session): void
    {
        $tokens = $request->session()->get($this->historyKey($qrCode), []);

        if (! in_array($session->session_token, $tokens, true)) {
            // A handful is plenty: older ones are past the history window anyway.
            $request->session()->put($this->historyKey($qrCode), array_slice([...$tokens, $session->session_token], -self::HISTORY_SESSIONS));
        }
    }

    /**
     * Orders this phone sent (or joined) at the table that are still open or
     * ended less than qr_history_minutes ago, newest first. Tokens whose
     * history expired are forgotten.
     *
     * @return Collection<int, QrSession>
     */
    private function history(Request $request, QrCode $qrCode): Collection
    {
        $tokens = $request->session()->get($this->historyKey($qrCode), []);

        if ($tokens === []) {
            return collect();
        }

        $sessions = QrSession::with(['order.diningTable', 'order.payment', 'order.items.product', 'order.items.bundle'])
            ->whereIn('session_token', $tokens)
            ->where('qr_token', $qrCode->qr_token)
            ->get();

        $visible = $sessions->filter(fn (QrSession $session): bool => $session->historyIsVisible());

        // Keep sessions without an order yet (still ordering) and the visible ones.
        $keep = $sessions->filter(fn (QrSession $session): bool => $session->order_id === null && $session->isUsable())->merge($visible)
            ->pluck('session_token')->unique()->values()->all();
        $request->session()->put($this->historyKey($qrCode), $keep);

        // Friends' phones may follow the same order: list it once.
        return $visible->unique('order_id')->sortByDesc(fn (QrSession $session): int => $session->order_id)->values();
    }

    private function unavailableReason(QrCode $qrCode): ?string
    {
        if (! $qrCode->is_active) {
            return $qrCode->disabled_reason ?: 'Mã QR của bàn này đang tạm ngưng. Vui lòng gọi nhân viên để gọi món.';
        }

        if ($qrCode->diningTable->status === TableStatus::Maintenance) {
            return 'Bàn này đang bảo trì. Vui lòng gọi nhân viên để được xếp bàn khác.';
        }

        return null;
    }

    private function unavailable(string $message, ?int $tableNo = null): View
    {
        return view('qr.unavailable', ['message' => $message, 'tableNo' => $tableNo]);
    }
}
