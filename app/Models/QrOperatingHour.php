<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * When customers may order by QR, per day of the week. Replaces the
 * `is_qr_operating()` function the SQL script meant to create but never did.
 *
 *  - closing after opening: open from opening to closing that day (07:00 → 22:00).
 *  - closing before opening: overnight, e.g. 18:00 → 02:00 runs into the next morning.
 *  - closing equal to opening: open all day.
 *  - no row for a day (hours never set up): open all day.
 */
#[Fillable(['day_of_week', 'opening_time', 'closing_time', 'is_open'])]
class QrOperatingHour extends Model
{
    /** Monday first, as shown on the settings page. */
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public const DAY_LABELS = [
        'Monday' => 'Thứ Hai', 'Tuesday' => 'Thứ Ba', 'Wednesday' => 'Thứ Tư', 'Thursday' => 'Thứ Năm',
        'Friday' => 'Thứ Sáu', 'Saturday' => 'Thứ Bảy', 'Sunday' => 'Chủ nhật',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
        ];
    }

    public static function isOpenAt(CarbonInterface $at): bool
    {
        $hours = static::all()->keyBy('day_of_week');
        $time = $at->format('H:i:s');
        $today = $hours->get($at->englishDayOfWeek);

        if (! $today) {
            return true;
        }

        if ($today->is_open && $today->coversTimeOfItsOwnDay($time)) {
            return true;
        }

        // The tail of yesterday's overnight hours (e.g. 00:30 when yesterday ran 18:00 → 02:00).
        $yesterday = $hours->get($at->copy()->subDay()->englishDayOfWeek);

        return $yesterday !== null && $yesterday->is_open && $yesterday->isOvernight() && $time < $yesterday->closingTime();
    }

    /**
     * "7:00 – 22:00" for a day, "Nghỉ" when closed, null when hours were never set.
     */
    public static function labelFor(CarbonInterface $day): ?string
    {
        /** @var Collection<int, self> $hours */
        $hours = static::where('day_of_week', $day->englishDayOfWeek)->get();
        $row = $hours->first();

        if (! $row) {
            return null;
        }

        if (! $row->is_open) {
            return 'Nghỉ';
        }

        return $row->openingTime() === $row->closingTime()
            ? 'Cả ngày'
            : self::short($row->openingTime()).' – '.self::short($row->closingTime());
    }

    public function openingTime(): string
    {
        return substr((string) $this->opening_time, 0, 5).':00';
    }

    public function closingTime(): string
    {
        return substr((string) $this->closing_time, 0, 5).':00';
    }

    public function isOvernight(): bool
    {
        return $this->closingTime() < $this->openingTime();
    }

    private function coversTimeOfItsOwnDay(string $time): bool
    {
        return match (true) {
            $this->openingTime() === $this->closingTime() => true,
            $this->isOvernight() => $time >= $this->openingTime(),
            default => $time >= $this->openingTime() && $time < $this->closingTime(),
        };
    }

    private static function short(string $time): string
    {
        return (string) preg_replace('/^0(\d)/', '$1', substr($time, 0, 5)); // 07:00 → 7:00, 00:30 → 0:30
    }
}
