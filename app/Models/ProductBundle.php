<?php

namespace App\Models;

use App\Models\Concerns\GeneratesSlug;
use App\Models\Concerns\HasImage;
use Database\Factories\ProductBundleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A combo sold at one price, e.g. "Cà phê + Bánh mì".
 */
#[Fillable(['name', 'description', 'bundle_price', 'individual_price', 'discount_amount', 'image_url', 'is_active', 'display_order'])]
class ProductBundle extends Model
{
    /** @use HasFactory<ProductBundleFactory> */
    use GeneratesSlug, HasFactory, HasImage;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bundle_price' => 'decimal:2',
            'individual_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<ProductBundleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_id')->orderBy('display_order')->orderBy('id');
    }
}
