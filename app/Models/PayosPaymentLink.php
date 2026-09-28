<?php

namespace App\Models;

use App\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payOS payment link created for an order. `status` holds payOS's own
 * value: PENDING, PROCESSING, PAID, CANCELLED or EXPIRED.
 */
#[Fillable([
    'order_id', 'order_code', 'payment_link_id', 'amount', 'description', 'bin', 'account_number', 'account_name',
    'checkout_url', 'qr_code', 'status', 'amount_paid', 'reference', 'transactions', 'paid_at', 'expires_at', 'created_by',
    'refunded_at', 'refund_method', 'refund_reference', 'refund_note', 'refunded_by',
])]
class PayosPaymentLink extends Model
{
    public const PENDING = 'PENDING';

    public const PROCESSING = 'PROCESSING';

    public const PAID = 'PAID';

    public const CANCELLED = 'CANCELLED';

    public const EXPIRED = 'EXPIRED';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_code' => 'integer',
            'amount' => 'integer',
            'amount_paid' => 'integer',
            'transactions' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'refunded_at' => 'datetime',
            'refund_method' => PaymentMethod::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    /**
     * Money arrived through this link but did not become the order's payment
     * (the order was settled another way or closed) and was not given back yet.
     */
    public function needsRefund(): bool
    {
        return $this->status === self::PAID
            && $this->refunded_at === null
            && $this->order->payment?->transaction_id !== "payos-{$this->order_code}";
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Links the customer may still pay.
     */
    #[Scope]
    protected function awaitingPayment(Builder $query): void
    {
        $query->whereIn('status', [self::PENDING, self::PROCESSING]);
    }

    public function isAwaitingPayment(): bool
    {
        return in_array($this->status, [self::PENDING, self::PROCESSING], true);
    }

    public function isFinal(): bool
    {
        return ! $this->isAwaitingPayment();
    }

    /**
     * A fresh orderCode: positive, unique and never reused (payOS refuses duplicates),
     * e.g. 260927001044123 = yymmddHHiiss + 3 random digits.
     */
    public static function newOrderCode(): int
    {
        return (int) (now()->format('ymdHis').str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT));
    }

    /**
     * Transfer description: ASCII, at most 9 characters so it also works for
     * payOS accounts not linked to a bank (e.g. ORD-2026-0042 => "DH260042").
     */
    public static function descriptionFor(Order $order): string
    {
        // "20260042" => year "26" + sequence "0042"
        $digits = preg_replace('/\D/', '', $order->order_number);

        return 'DH'.substr($digits, 2, 2).substr($digits, 4);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING => 'Đang chờ khách chuyển khoản',
            self::PROCESSING => 'Ngân hàng đang xử lý',
            self::PAID => 'Đã nhận tiền',
            self::CANCELLED => 'Đã hủy mã QR',
            self::EXPIRED => 'Mã QR đã hết hạn',
            default => $this->status,
        };
    }
}
