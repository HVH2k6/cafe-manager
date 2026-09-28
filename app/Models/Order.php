<?php

namespace App\Models;

use App\Observers\OrderObserver;
use App\OrderItemStatus;
use App\OrderStatus;
use App\OrderType;
use App\PaymentMethod;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'order_number', 'user_id', 'table_id', 'qr_session_id', 'order_type', 'status', 'subtotal', 'discount_amount',
    'discount_reason', 'total_amount', 'notes', 'customer_phone', 'customer_name', 'payment_preference', 'estimated_ready_at',
    'started_at', 'completed_at', 'cancelled_reason', 'cancelled_by',
])]
#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'order_type' => 'dine_in',
        'subtotal' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'payment_preference' => PaymentMethod::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'estimated_ready_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * Not named table(): Eloquent's $table property already holds the DB table name.
     *
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    /**
     * Staff member who created the order; null when the customer sent it by QR.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * QR session that sent the order (the first customer's phone).
     *
     * @return BelongsTo<QrSession, $this>
     */
    public function qrSession(): BelongsTo
    {
        return $this->belongsTo(QrSession::class);
    }

    /**
     * Every QR session following this order: other phones at the table that added items to it too.
     *
     * @return HasMany<QrSession, $this>
     */
    public function followingQrSessions(): HasMany
    {
        return $this->hasMany(QrSession::class);
    }

    public function isFromQr(): bool
    {
        return $this->qr_session_id !== null;
    }

    /**
     * "Nguyễn An" for staff orders, "Khách (QR)" for orders customers sent themselves.
     */
    public function creatorLabel(): string
    {
        return $this->creator?->name ?? ($this->isFromQr() ? 'Khách (QR)' : '—');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * @return HasMany<PayosPaymentLink, $this>
     */
    public function payosLinks(): HasMany
    {
        return $this->hasMany(PayosPaymentLink::class)->latest('id');
    }

    public function isPaid(): bool
    {
        return $this->payment()->where('status', Payment::STATUS_COMPLETED)->exists();
    }

    /**
     * Why the order's items or amount may not change right now, or null if they may.
     * A paid order is settled, and a QR code waiting for a transfer carries the old amount.
     */
    public function amountLockReason(): ?string
    {
        if ($this->isPaid()) {
            return "Đơn {$this->order_number} đã thanh toán, không sửa món hay giảm giá được nữa.";
        }

        if ($this->payosLinks()->awaitingPayment()->exists()) {
            return "Đơn {$this->order_number} đang chờ khách chuyển khoản. Hãy hủy mã QR trước khi sửa đơn.";
        }

        return null;
    }

    /**
     * Orders still being handled (not completed or cancelled).
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', OrderStatus::open());
    }

    /**
     * The next number of the year, e.g. ORD-2026-0042. Must run inside the
     * transaction that inserts the order: the locking read sees numbers other
     * transactions have just committed, and the unique index is the last guard.
     */
    public static function nextOrderNumber(): string
    {
        $prefix = 'ORD-'.now()->year.'-';

        $last = static::query()
            ->where('order_number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(order_number) DESC') // so ORD-2026-10000 sorts after ORD-2026-9999
            ->orderByDesc('order_number')
            ->lockForUpdate()
            ->value('order_number');

        $sequence = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Subtotal of the items that are not cancelled; total = subtotal − discount (never below 0).
     */
    public function recalculateTotals(): void
    {
        $subtotal = $this->items()
            ->where('status', '!=', OrderItemStatus::Cancelled)
            ->get()
            ->sum(fn (OrderItem $item): float => $item->lineTotal());

        $this->subtotal = $subtotal;
        $this->total_amount = max(0, $subtotal - (float) $this->discount_amount);
        $this->save();
    }

    /**
     * Move a confirmed order along with its items: preparing once the kitchen
     * starts, ready when every item is done, served when everything went out.
     * Pending (unconfirmed), completed and cancelled orders are left alone.
     */
    public function syncStatusWithItems(): void
    {
        if (! in_array($this->status, [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Served], true)) {
            return;
        }

        $statuses = $this->items()->where('status', '!=', OrderItemStatus::Cancelled)->pluck('status');

        if ($statuses->isEmpty()) {
            return;
        }

        $this->status = match (true) {
            $statuses->every(fn (OrderItemStatus $status): bool => $status === OrderItemStatus::Served) => OrderStatus::Served,
            $statuses->every(fn (OrderItemStatus $status): bool => in_array($status, [OrderItemStatus::Ready, OrderItemStatus::Served], true)) => OrderStatus::Ready,
            $statuses->contains(fn (OrderItemStatus $status): bool => $status !== OrderItemStatus::Pending) => OrderStatus::Preparing,
            default => OrderStatus::Confirmed,
        };

        if ($this->status === OrderStatus::Preparing && $this->started_at === null) {
            $this->started_at = now();
        }

        $this->save();
    }

    /**
     * "Bàn 3", "Mang về"…
     */
    public function placeLabel(): string
    {
        return $this->diningTable ? "Bàn {$this->diningTable->table_no}" : $this->order_type->label();
    }
}
