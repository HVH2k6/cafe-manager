<?php

namespace App\Actions\Orders;

use App\InventoryTransactionType;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Holds stock for open orders and takes it out when an order is completed.
 * Products without an inventory row are not stock-tracked and are skipped.
 * Every method must run inside a database transaction: the stock rows are
 * locked (in product order, to avoid deadlocks) until it commits.
 */
class StockReservation
{
    /**
     * Hold stock for new order lines, refusing lines that exceed what is available.
     *
     * @param  iterable<OrderItem>  $items
     *
     * @throws ValidationException
     */
    public function reserve(iterable $items): void
    {
        $quantities = $this->quantities($items);

        foreach ($this->lockedStock($quantities) as $inventory) {
            $wanted = $quantities[$inventory->product_id];

            if ($wanted > $inventory->availableQuantity()) {
                throw ValidationException::withMessages([
                    'items' => $inventory->availableQuantity() > 0
                        ? "“{$inventory->product->name}” chỉ còn {$inventory->availableQuantity()} {$inventory->unitLabel()}."
                        : "“{$inventory->product->name}” đã hết hàng.",
                ]);
            }

            $inventory->increment('quantity_reserved', $wanted);
        }
    }

    /**
     * Give back stock held for lines that will not be made (cancelled).
     *
     * @param  iterable<OrderItem>  $items
     */
    public function release(iterable $items): void
    {
        $quantities = $this->quantities($items);

        foreach ($this->lockedStock($quantities) as $inventory) {
            $inventory->quantity_reserved = max(0, $inventory->quantity_reserved - $quantities[$inventory->product_id]);
            $inventory->save();
        }
    }

    /**
     * Take the held stock out for a completed order and log each product as a sale.
     *
     * @param  iterable<OrderItem>  $items
     */
    public function consume(iterable $items, Order $order, ?User $by): void
    {
        $quantities = $this->quantities($items);

        foreach ($this->lockedStock($quantities) as $inventory) {
            $sold = min($quantities[$inventory->product_id], max(0, $inventory->quantity_on_hand));

            $inventory->quantity_on_hand -= $sold;
            $inventory->quantity_reserved = max(0, $inventory->quantity_reserved - $quantities[$inventory->product_id]);
            $inventory->save();

            $inventory->product->inventoryTransactions()->create([
                'transaction_type' => InventoryTransactionType::Sale,
                'quantity' => -$sold,
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'notes' => "Đơn {$order->order_number}",
                'created_by' => $by?->id,
            ]);
        }
    }

    /**
     * @param  iterable<OrderItem>  $items
     * @return array<int, int> product id => total units
     */
    private function quantities(iterable $items): array
    {
        $totals = [];

        foreach ($items as $item) {
            foreach ($item->stockQuantities() as $productId => $quantity) {
                $totals[$productId] = ($totals[$productId] ?? 0) + $quantity;
            }
        }

        return $totals;
    }

    /**
     * @param  array<int, int>  $quantities
     * @return Collection<int, Inventory>
     */
    private function lockedStock(array $quantities): Collection
    {
        if ($quantities === []) {
            return new Collection;
        }

        return Inventory::with('product')
            ->whereIn('product_id', array_keys($quantities))
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get();
    }
}
