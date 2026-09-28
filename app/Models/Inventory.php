<?php

namespace App\Models;

use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock of one product. Only products that have a row here are stock-tracked.
 * Quantities change only through InventoryTransactionController so every
 * change leaves an inventory_transactions record.
 */
#[Fillable(['product_id', 'min_stock', 'max_stock', 'reorder_level', 'unit_of_measure'])]
class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    /**
     * Common units; anything else typed by the user is shown as-is.
     */
    public const UNIT_LABELS = [
        'piece' => 'cái',
        'cup' => 'ly',
        'bottle' => 'chai',
        'can' => 'lon',
        'bag' => 'túi',
        'box' => 'hộp',
        'kg' => 'kg',
        'g' => 'g',
        'l' => 'lít',
        'ml' => 'ml',
    ];

    protected $table = 'inventory';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity_on_hand' => 0,
        'quantity_reserved' => 0,
        'min_stock' => 5,
        'max_stock' => 100,
        'reorder_level' => 20,
        'unit_of_measure' => 'piece',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'min_stock' => 'integer',
            'max_stock' => 'integer',
            'reorder_level' => 'integer',
            'last_restock' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Stock below its minimum (same rule as the `low_stock_products` view).
     */
    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->whereColumn('quantity_on_hand', '<', 'min_stock');
    }

    public static function lowStockCount(): int
    {
        return static::query()->lowStock()->count();
    }

    /**
     * Stock not held for pending orders.
     */
    public function availableQuantity(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved);
    }

    /**
     * @return 'out'|'low'|'ok'
     */
    public function stockLevel(): string
    {
        return match (true) {
            $this->quantity_on_hand <= 0 => 'out',
            $this->quantity_on_hand < $this->min_stock => 'low',
            default => 'ok',
        };
    }

    public function unitLabel(): string
    {
        return self::UNIT_LABELS[$this->unit_of_measure] ?? $this->unit_of_measure;
    }
}
