<?php

namespace App\Models;

use App\QrNotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something that happened in a QR session: the order was received / started /
 * finished, or the customer called staff (answered once `is_read`).
 */
#[Fillable(['qr_session_id', 'order_id', 'type', 'title', 'message', 'action_url', 'is_read', 'is_sent', 'read_at'])]
class QrNotification extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_read' => false,
        'is_sent' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QrNotificationType::class,
            'is_read' => 'boolean',
            'is_sent' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<QrSession, $this>
     */
    public function qrSession(): BelongsTo
    {
        return $this->belongsTo(QrSession::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
