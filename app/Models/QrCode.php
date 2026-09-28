<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The QR code stuck on a table. Its token is the only secret in the URL, so
 * generating a new one makes every printed copy of the old code stop working.
 */
#[Fillable(['table_id', 'qr_token', 'qr_data', 'qr_url', 'is_active', 'disabled_reason'])]
class QrCode extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    /**
     * Create the table's code, or replace its token (the old printed code stops working).
     */
    public static function issueFor(DiningTable $table): self
    {
        $token = Str::random(32);

        return static::updateOrCreate(['table_id' => $table->id], [
            'qr_token' => $token,
            'qr_url' => static::urlFor($token),
            'is_active' => true,
            'disabled_reason' => null,
        ]);
    }

    /**
     * Address the printed code opens, on the address customers' phones can reach
     * (APP_PUBLIC_URL). Worked out again each time in case that address changed.
     */
    public function url(): string
    {
        return static::urlFor($this->qr_token);
    }

    private static function urlFor(string $token): string
    {
        $root = rtrim((string) (config('app.public_url') ?: config('app.url')), '/');

        return $root.route('qr.show', ['qrCode' => $token], absolute: false);
    }
}
