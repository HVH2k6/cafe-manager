<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The date range a dashboard or report covers: today, the last 7 / 30 days
 * (today included) or a custom range. `from` is the start of its day, `to`
 * the end of its day.
 */
final class ReportPeriod
{
    public const PRESETS = [
        'today' => 'Hôm nay',
        '7d' => '7 ngày',
        '30d' => '30 ngày',
        'custom' => 'Tùy chọn',
    ];

    /** Longest custom range, so a typo (year 2000) does not scan every order. */
    public const MAX_DAYS = 366;

    public function __construct(
        public readonly string $key,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function fromRequest(Request $request, string $default = 'today'): self
    {
        $key = array_key_exists((string) $request->query('period'), self::PRESETS) ? (string) $request->query('period') : $default;

        if ($key === 'custom') {
            $from = self::parseDate($request->query('from'));
            $to = self::parseDate($request->query('to'));

            if ($from || $to) {
                return self::between($from ?? $to, $to ?? $from);
            }

            $key = $default;
        }

        return self::preset($key);
    }

    public static function preset(string $key): self
    {
        $today = CarbonImmutable::today();

        return match ($key) {
            '7d' => new self('7d', $today->subDays(6), $today->endOfDay()),
            '30d' => new self('30d', $today->subDays(29), $today->endOfDay()),
            default => new self('today', $today, $today->endOfDay()),
        };
    }

    /**
     * A custom range; reversed dates are swapped and a range longer than MAX_DAYS is cut at its end.
     */
    public static function between(CarbonImmutable $from, CarbonImmutable $to): self
    {
        [$from, $to] = $from->greaterThan($to) ? [$to, $from] : [$from, $to];

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS - 1);
        }

        return new self('custom', $from->startOfDay(), $to->endOfDay());
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to->startOfDay()) + 1;
    }

    public function isSingleDay(): bool
    {
        return $this->days() === 1;
    }

    /**
     * "Hôm nay", "7 ngày qua (23/09 – 29/09)", "01/09/2026 – 15/09/2026"…
     */
    public function label(): string
    {
        $range = $this->from->format('d/m').' – '.$this->to->format('d/m');

        return match ($this->key) {
            'today' => 'Hôm nay',
            '7d' => "7 ngày qua ({$range})",
            '30d' => "30 ngày qua ({$range})",
            default => $this->isSingleDay()
                ? 'Ngày '.$this->from->format('d/m/Y')
                : $this->from->format('d/m/Y').' – '.$this->to->format('d/m/Y'),
        };
    }

    /**
     * Query string that reproduces this period (for links and exports).
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return $this->key === 'custom'
            ? ['period' => 'custom', 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString()]
            : ['period' => $this->key];
    }

    /**
     * "2026-09-01_2026-09-29", used in export file names.
     */
    public function slug(): string
    {
        return $this->from->toDateString().'_'.$this->to->toDateString();
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', $value), null, false);

        // createFromFormat rolls 2026-13-45 over into a real date: only keep dates that round-trip.
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
