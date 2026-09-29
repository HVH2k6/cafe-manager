<?php

namespace App\Models;

use App\QrSessionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One customer's visit after scanning a table's QR code: their cart, the order
 * it became and their "call staff" requests. The random session token in the
 * URL is what lets the phone act on the session; nobody logs in.
 */
#[Fillable([
    'table_id', 'qr_token', 'session_token', 'order_id', 'status', 'cart_data', 'ip_address', 'user_agent',
    'last_activity', 'expires_at', 'completed_at',
])]
class QrSession extends Model
{
    /** Idle minutes before an unused session expires. */
    public const LIFETIME_MINUTES = 30;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QrSessionStatus::class,
            'cart_data' => 'array',
            'last_activity' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'session_token';
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<QrCode, $this>
     */
    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class, 'qr_token', 'qr_token');
    }

    /**
     * @return HasMany<QrNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(QrNotification::class)->latest('id');
    }

    public static function start(QrCode $code, Request $request): self
    {
        return static::create([
            'table_id' => $code->table_id,
            'qr_token' => $code->qr_token,
            'session_token' => Str::random(48),
            'status' => QrSessionStatus::Active,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'last_activity' => now(),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ]);
    }

    /**
     * Whether the customer can still order and call staff with this session:
     * active, and either recently used or following an order that is still open.
     */
    public function isUsable(): bool
    {
        if ($this->status !== QrSessionStatus::Active) {
            return false;
        }

        return $this->expires_at?->isFuture() || ($this->order?->status->isOpen() ?? false);
    }

    /**
     * Until when the phone may still look back at this session's order once it
     * is over (Setting qr_history_minutes after it was completed or cancelled),
     * so the next customers at the table never see it. Null while the order is open.
     */
    public function historyExpiresAt(): ?CarbonInterface
    {
        $order = $this->order;

        if (! $order || $order->status->isOpen()) {
            return null;
        }

        $closedAt = $order->completed_at ?? $this->completed_at ?? $order->updated_at;

        return $closedAt->copy()->addMinutes((int) Setting::get('qr_history_minutes'));
    }

    /**
     * Whether the phone may still see this session's order: open, or closed less than the history window ago.
     */
    public function historyIsVisible(): bool
    {
        return $this->order !== null && ($this->historyExpiresAt()?->isFuture() ?? true);
    }

    /**
     * Push the expiry back after the customer did something.
     */
    public function touchActivity(): void
    {
        $this->forceFill([
            'last_activity' => now(),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ])->save();
    }

    /**
     * Public WebSocket channel of this session. Customers have no login, so the
     * channel is public; its name carries the unguessable session token.
     */
    public function channelName(): string
    {
        return "qr-session.{$this->session_token}";
    }
}
