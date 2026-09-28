<?php

namespace App\Http\Controllers\Qr;

use App\Actions\Qr\CallStaff;
use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Models\QrOperatingHour;
use App\Models\QrSession;
use App\QrNotificationType;
use App\TableStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The page a table's QR code opens (no login): which table, whether the cafe
 * takes QR orders right now, and a session to order or call staff with.
 */
class QrSessionController extends Controller
{
    public function show(Request $request, QrCode $qrCode): View
    {
        $table = $qrCode->diningTable;

        if ($unavailable = $this->unavailableReason($qrCode)) {
            return $this->unavailable($unavailable, $table->table_no);
        }

        $session = $this->currentSession($request, $qrCode);

        return view('qr.landing', [
            'qrCode' => $qrCode,
            'table' => $table,
            'session' => $session,
            'openOrder' => $session?->order?->status->isOpen() ? $session->order : null,
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
