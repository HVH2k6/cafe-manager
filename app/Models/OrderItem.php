<?php

namespace App\Models;

use App\OrderItemStatus;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an order: a product (with chosen variants) or a combo.
 * `subtotal` is a generated column in the database and is never written.
 */
#[Fillable([
    'order_id', 'product_id', 'bundle_id', 'quantity', 'unit_price', 'variant_ids', 'variant_values',
    'special_instructions', 'status', 'prepared_by', 'prepared_at', 'served_at',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'variant_ids' => 'array',
            'variant_values' => 'array',
            'status' => OrderItemStatus::class,
            'prepared_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductBundle, $this>
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'bundle_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function name(): string
    {
        return $this->bundle?->name ?? $this->product?->name ?? 'Món đã xóa';
    }

    public function lineTotal(): float
    {
        return $this->quantity * (float) $this->unit_price;
    }

    /**
     * Chosen options, e.g. "Size: Lớn · Đường: Ít".
     */
    public function variantSummary(): string
    {
        return collect($this->variant_values ?? [])
            ->map(fn (array $variant): string => "{$variant['name']}: {$variant['value']}")
            ->implode(' · ');
    }

    /**
     * Units of each product this line takes out of stock; a combo counts its components.
     *
     * @return array<int, int> product id => quantity
     */
    public function stockQuantities(): array
    {
        if ($this->product_id !== null) {
            return [$this->product_id => $this->quantity];
        }

        return $this->bundle?->items
            ->mapWithKeys(fn (ProductBundleItem $item): array => [$item->product_id => $item->quantity * $this->quantity])
            ->all() ?? [];
    }
}
