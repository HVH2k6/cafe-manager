<?php

namespace App\Http\Controllers;

use App\Models\QrOperatingHour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hours during which customers may order by QR, per day of the week.
 */
class QrOperatingHourController extends Controller
{
    public function edit(): View
    {
        $saved = QrOperatingHour::all()->keyBy('day_of_week');

        return view('settings.qr-hours', [
            'days' => collect(QrOperatingHour::DAYS)->map(fn (string $day): array => [
                'day' => $day,
                'label' => QrOperatingHour::DAY_LABELS[$day],
                'is_open' => $saved->get($day)?->is_open ?? true,
                'opening_time' => substr($saved->get($day)?->openingTime() ?? '07:00', 0, 5),
                'closing_time' => substr($saved->get($day)?->closingTime() ?? '22:00', 0, 5),
            ]),
            'isConfigured' => $saved->isNotEmpty(),
            'isOpenNow' => QrOperatingHour::isOpenAt(now()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];

        foreach (QrOperatingHour::DAYS as $day) {
            $rules["days.{$day}.is_open"] = ['nullable', 'boolean'];
            $rules["days.{$day}.opening_time"] = ['required', 'date_format:H:i'];
            $rules["days.{$day}.closing_time"] = ['required', 'date_format:H:i'];
        }

        $validated = $request->validate($rules, [
            'days.*.opening_time.required' => 'Vui lòng nhập giờ mở.',
            'days.*.closing_time.required' => 'Vui lòng nhập giờ đóng.',
            'days.*.opening_time.date_format' => 'Giờ không hợp lệ (HH:MM).',
            'days.*.closing_time.date_format' => 'Giờ không hợp lệ (HH:MM).',
        ]);

        foreach (QrOperatingHour::DAYS as $day) {
            QrOperatingHour::updateOrCreate(['day_of_week' => $day], [
                'is_open' => (bool) ($validated['days'][$day]['is_open'] ?? false),
                'opening_time' => $validated['days'][$day]['opening_time'].':00',
                'closing_time' => $validated['days'][$day]['closing_time'].':00',
            ]);
        }

        return back()->with('status', 'Đã lưu giờ nhận đơn QR.');
    }
}
