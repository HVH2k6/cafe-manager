<?php

namespace App\Actions\Orders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductVariant;

/**
 * What can be sold right now, as plain data for the staff POS and the
 * customers' QR menu. Only for display: orders are priced again on the server.
 */
class LoadMenu
{
    /**
     * @return array{categories: list<array<string, mixed>>, bundles: list<array<string, mixed>>}
     */
    public function handle(): array
    {
        $categories = Category::where('is_active', true)
            ->with(['products' => fn ($query) => $query->where('is_available', true)->with(['variants', 'inventory'])->orderBy('display_order')->orderBy('name')])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category): bool => $category->products->isNotEmpty());

        return [
            'categories' => $categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'products' => $category->products->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => (int) $product->selling_price,
                    'image' => $product->imageUrl(),
                    'stock' => $product->inventory?->availableQuantity(),
                    'variantGroups' => $product->variants
                        ->groupBy('name')
                        ->map(fn ($variants, string $group): array => [
                            'name' => $group,
                            'options' => $variants->map(fn (ProductVariant $variant): array => [
                                'id' => $variant->id,
                                'value' => $variant->value,
                                'priceModifier' => (int) $variant->price_modifier,
                                'isDefault' => $variant->is_default,
                            ])->values()->all(),
                        ])->values()->all(),
                ])->all(),
            ])->values()->all(),
            'bundles' => ProductBundle::where('is_active', true)->with('items.product')->orderBy('display_order')->orderBy('name')->get()
                ->map(function (ProductBundle $bundle): array {
                    $separately = (int) $bundle->items->sum(fn (ProductBundleItem $item): float => $item->quantity * (float) $item->product->selling_price);

                    return [
                        'id' => $bundle->id,
                        'name' => $bundle->name,
                        'description' => $bundle->description,
                        'price' => (int) $bundle->bundle_price,
                        'priceSeparately' => $separately,
                        'saving' => max(0, $separately - (int) $bundle->bundle_price),
                        'image' => $bundle->imageUrl(),
                        'contents' => $bundle->items->map(fn (ProductBundleItem $item): string => ($item->quantity > 1 ? "{$item->quantity}× " : '').$item->product->name)->implode(', '),
                        'components' => $bundle->items->map(fn (ProductBundleItem $item): string => ($item->quantity > 1 ? "{$item->quantity}× " : '').$item->product->name)->values()->all(),
                    ];
                })->all(),
        ];
    }
}
