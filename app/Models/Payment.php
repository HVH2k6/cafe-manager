<?php

namespace App\Models;

use App\PaymentMethod;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The payment that settled an order (one per order, `order_id` is unique).
 */
#[Fillable([
    'order_id', 'amount', 'method', 'status', 'transaction_id', 'payment_gateway', 'gateway_response', 'paid_amount',
    'change_amount', 'card_last4', 'reference_number', 'notes', 'processed_by', 'paid_at',
    'refunded_at', 'refund_reason', 'refund_amount', 'refund_method', 'refund_reference', 'refunded_by',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REFUNDED = 'refunded';

    /**
     * Part of the payment was given back (the schema's `partial`).
     */
    public const STATUS_PARTIALLY_REFUNDED = 'partial';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'gateway_response' => 'array',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'refund_amount' => 'decimal:2',
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

    public function isRefunded(): bool
    {
        return $this->refunded_at !== null;
    }

    /**
     * What the customer effectively paid after any refund.
     */
    public function netAmount(): float
    {
        return (float) $this->amount - (float) $this->refund_amount;
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
