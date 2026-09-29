<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cafe settings (step 10): name, address and phone printed on receipts,
 * opening hours shown on the dashboard, and how long a customer's phone keeps
 * the history of what they ordered by QR.
 */
class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.general', ['settings' => Setting::values()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cafe_name' => ['required', 'string', 'max:100'],
            'cafe_address' => ['nullable', 'string', 'max:255'],
            'cafe_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s().-]+$/'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'receipt_note' => ['nullable', 'string', 'max:500'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i'],
            'qr_history_minutes' => ['required', 'integer', 'min:'.Setting::QR_HISTORY_MIN, 'max:'.Setting::QR_HISTORY_MAX],
        ], [
            'cafe_name.required' => 'Vui lòng nhập tên quán.',
            'cafe_phone.regex' => 'Số điện thoại chỉ gồm số và các ký tự + ( ) - .',
            'opening_time.date_format' => 'Giờ không hợp lệ (HH:MM).',
            'closing_time.date_format' => 'Giờ không hợp lệ (HH:MM).',
            'qr_history_minutes.min' => 'Tối thiểu :min phút.',
            'qr_history_minutes.max' => 'Tối đa :max phút.',
        ]);

        $before = Setting::values();
        Setting::putMany($validated);

        $changed = collect($validated)->filter(fn ($value, string $key): bool => (string) ($before[$key] ?? '') !== (string) ($value ?? ''));

        if ($changed->isNotEmpty()) {
            ActivityLogger::log('settings.updated', 'Sửa cài đặt quán: '.$changed->keys()->map(fn (string $key): string => self::LABELS[$key])->implode(', '),
                null, $changed->mapWithKeys(fn ($value, string $key): array => [$key => $before[$key] ?? null])->all(), $changed->all());
        }

        return back()->with('status', 'Đã lưu cài đặt.');
    }

    /** Field => label, for the activity log. */
    public const LABELS = [
        'cafe_name' => 'tên quán',
        'cafe_address' => 'địa chỉ',
        'cafe_phone' => 'số điện thoại',
        'receipt_footer' => 'lời cảm ơn trên hóa đơn',
        'receipt_note' => 'ghi chú hóa đơn',
        'opening_time' => 'giờ mở cửa',
        'closing_time' => 'giờ đóng cửa',
        'qr_history_minutes' => 'thời gian giữ lịch sử QR',
    ];
}
