<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Models\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Table QR codes: create, print, replace (the old printed code stops working)
 * and pause. The code image is drawn in the browser; only its URL is stored.
 */
class QrCodeController extends Controller
{
    /**
     * Printable sheet with every table's code.
     */
    public function index(): View
    {
        $tables = DiningTable::with('qrCode')->orderBy('table_no')->get();

        return view('tables.qr-codes', [
            'tables' => $tables,
            'missingCount' => $tables->filter(fn (DiningTable $table): bool => $table->qrCode === null)->count(),
        ]);
    }

    public function show(DiningTable $table): View
    {
        return view('tables.qr', [
            'table' => $table->load('qrCode'),
        ]);
    }

    /**
     * Create the table's code, or replace it with a new token.
     */
    public function store(DiningTable $table): RedirectResponse
    {
        $replacing = $table->qrCode()->exists();

        QrCode::issueFor($table);

        return to_route('tables.qr.show', $table)->with('status', $replacing
            ? "Đã tạo mã QR mới cho bàn {$table->table_no}. Mã cũ đã in sẽ không dùng được nữa — hãy in và dán mã mới."
            : "Đã tạo mã QR cho bàn {$table->table_no}.");
    }

    /**
     * Create codes for every table that has none yet.
     */
    public function storeMissing(): RedirectResponse
    {
        $tables = DiningTable::doesntHave('qrCode')->get();

        $tables->each(fn (DiningTable $table) => QrCode::issueFor($table));

        return to_route('tables.qr.index')->with('status', "Đã tạo mã QR cho {$tables->count()} bàn.");
    }

    public function updateStatus(Request $request, DiningTable $table): RedirectResponse
    {
        $code = $table->qrCode()->firstOrFail();

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'disabled_reason' => ['nullable', 'string', 'max:255'],
        ], [
            'disabled_reason.max' => 'Lý do tối đa 255 ký tự.',
        ]);

        $code->update([
            'is_active' => (bool) $validated['is_active'],
            'disabled_reason' => $validated['is_active'] ? null : ($validated['disabled_reason'] ?? null),
        ]);

        return back()->with('status', $code->is_active
            ? "Đã bật lại mã QR bàn {$table->table_no}."
            : "Đã tạm ngưng mã QR bàn {$table->table_no}. Khách quét sẽ được nhắn gọi nhân viên.");
    }
}
