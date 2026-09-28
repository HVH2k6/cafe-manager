<?php

namespace App\Actions\Orders;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns cart lines from the POS into unsaved order items, pricing them from
 * the current menu (never from the browser) and snapshotting chosen variants
 * so later menu edits do not change past orders.
 */
class BuildOrderItems
{
    /**
     * @param  list<array{type: string, id: int|string, quantity: int|string, variant_ids?: list<int|string>, note?: ?string}>  $lines
     * @return Collection<int, OrderItem>
     *
     * @throws ValidationException
     */
    public function handle(array $lines): Collection
    {
        $products = Product::with('variants')
            ->whereKey(collect($lines)->where('type', 'product')->pluck('id'))
            ->get()
            ->keyBy('id');
        $bundles = ProductBundle::with('items.product')
            ->whereKey(collect($lines)->where('type', 'bundle')->pluck('id'))
            ->get()
            ->keyBy('id');

        return collect($lines)->values()->map(fn (array $line, int $index): OrderItem => $line['type'] === 'bundle'
            ? $this->bundleItem($bundles->get((int) $line['id']), $line, $index)
            : $this->productItem($products->get((int) $line['id']), $line, $index));
    }

    /**
     * @param  array{id: int|string, quantity: int|string, variant_ids?: list<int|string>, note?: ?string}  $line
     */
    private function productItem(?Product $product, array $line, int $index): OrderItem
    {
        if (! $product || ! $product->is_available) {
            $this->fail($index, $product ? "“{$product->name}” đang ngừng bán." : 'Món này không còn trên thực đơn.');
        }

        $variantIds = array_map('intval', $line['variant_ids'] ?? []);
        $variants = $product->variants->whereIn('id', $variantIds);

        if ($variants->count() !== count(array_unique($variantIds))) {
            $this->fail($index, "Lựa chọn không hợp lệ cho “{$product->name}”.");
        }

        $duplicateGroup = $variants->groupBy(fn (ProductVariant $variant): string => mb_strtolower($variant->name))->first(fn ($group) => $group->count() > 1);

        if ($duplicateGroup) {
            $this->fail($index, "“{$product->name}”: chỉ chọn một {$duplicateGroup->first()->name}.");
        }

        return (new OrderItem([
            'product_id' => $product->id,
            'quantity' => (int) $line['quantity'],
            'unit_price' => (float) $product->selling_price + $variants->sum(fn (ProductVariant $variant): float => (float) $variant->price_modifier),
            'variant_ids' => $variants->pluck('id')->values()->all() ?: null,
            'variant_values' => $variants->map(fn (ProductVariant $variant): array => [
                'name' => $variant->name,
                'value' => $variant->value,
                'price_modifier' => (float) $variant->price_modifier,
            ])->values()->all() ?: null,
            'special_instructions' => filled($line['note'] ?? null) ? trim($line['note']) : null,
        ]))->setRelation('product', $product);
    }

    /**
     * @param  array{id: int|string, quantity: int|string, note?: ?string}  $line
     */
    private function bundleItem(?ProductBundle $bundle, array $line, int $index): OrderItem
    {
        if (! $bundle || ! $bundle->is_active) {
            $this->fail($index, $bundle ? "Combo “{$bundle->name}” đang tạm ngừng." : 'Combo này không còn trên thực đơn.');
        }

        return (new OrderItem([
            'bundle_id' => $bundle->id,
            'quantity' => (int) $line['quantity'],
            'unit_price' => $bundle->bundle_price,
            'special_instructions' => filled($line['note'] ?? null) ? trim($line['note']) : null,
        ]))->setRelation('bundle', $bundle);
    }

    /**
     * @throws ValidationException
     */
    private function fail(int $index, string $message): never
    {
        throw ValidationException::withMessages(["items.{$index}" => $message]);
    }
}
