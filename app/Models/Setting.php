<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cafe settings edited on the "Cài đặt" page (step 10), stored as key / value.
 * Keys never saved fall back to defaults (the .env values for the cafe details),
 * so an installation that never opened the page behaves as before.
 *
 * The cafe details are also copied into config('app.cafe_*') at boot, so views
 * reading config keep working.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    private const CACHE_KEY = 'settings.all';

    /** Setting key => config key it overrides. */
    private const CONFIG_KEYS = [
        'cafe_name' => 'app.cafe_name',
        'cafe_address' => 'app.cafe_address',
        'cafe_phone' => 'app.cafe_phone',
    ];

    /** Bounds for how long a phone keeps seeing its QR orders after they end. */
    public const QR_HISTORY_MIN = 5;

    public const QR_HISTORY_MAX = 120;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /** The .env values, read once before applyToConfig() overwrites them. */
    private static ?array $envDefaults = null;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        self::$envDefaults ??= [
            'cafe_name' => config('app.cafe_name') ?: 'Cafe ABC',
            'cafe_address' => config('app.cafe_address'),
            'cafe_phone' => config('app.cafe_phone'),
        ];

        return [
            ...self::$envDefaults,
            'receipt_footer' => 'Cảm ơn quý khách!',
            'receipt_note' => null,
            'opening_time' => '07:00',
            'closing_time' => '22:00',
            'qr_history_minutes' => 20,
        ];
    }

    public static function get(string $key): mixed
    {
        return self::values()[$key] ?? null;
    }

    /**
     * Every setting, saved values over defaults.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return [...self::defaults(), ...array_filter(self::storedValues(), fn (?string $value): bool => $value !== null)];
    }

    /**
     * Save several settings at once and apply them to this request.
     * An empty value goes back to the default.
     *
     * @param  array<string, mixed>  $values
     */
    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value === '' || $value === null ? null : (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
        self::applyToConfig();
    }

    /**
     * Copy the cafe details into config. Skipped while the table does not exist
     * yet (fresh install before `migrate`).
     */
    public static function applyToConfig(): void
    {
        self::defaults(); // remember the .env values first

        try {
            $values = self::values();
        } catch (Throwable) {
            return;
        }

        foreach (self::CONFIG_KEYS as $key => $configKey) {
            config([$configKey => $values[$key]]);
        }
    }

    /**
     * Saved rows, cached until the next save.
     *
     * @return array<string, ?string>
     */
    private static function storedValues(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => static::query()->pluck('value', 'key')->all());
    }
}
