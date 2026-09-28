<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryTransactionRequest;
use App\InventoryTransactionType;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryTransactionController extends Controller
{
    /**
     * Record a purchase, stock count, damage or expiry. The stock row is locked
     * so two people recording at the same time cannot overwrite each other.
     */
    public function store(StoreInventoryTransactionRequest $request, Product $product): RedirectResponse
    {
        $type = $request->type();
        $quantity = (int) $request->validated('quantity');

        $change = DB::transaction(function () use ($request, $product, $type, $quantity): int {
            $inventory = Inventory::where('product_id', $product->id)->lockForUpdate()->first()
                ?? Inventory::create(['product_id' => $product->id]);

            $change = match ($type) {
                InventoryTransactionType::Adjustment => $quantity - $inventory->quantity_on_hand,
                InventoryTransactionType::Purchase => $quantity,
                default => -$quantity,
            };

            if ($type === InventoryTransactionType::Adjustment && $change === 0) {
                throw ValidationException::withMessages(['quantity' => 'Số đếm được bằng đúng số tồn hiện tại, không cần điều chỉnh.']);
            }

            if ($inventory->quantity_on_hand + $change < 0) {
                throw ValidationException::withMessages(['quantity' => "Chỉ còn {$inventory->quantity_on_hand} {$inventory->unitLabel()} trong kho."]);
            }

            $inventory->quantity_on_hand += $change;

            if ($type === InventoryTransactionType::Purchase) {
                $inventory->last_restock = now();
            }

            $inventory->save();

            $product->inventoryTransactions()->create([
                'transaction_type' => $type,
                'quantity' => $change,
                'reference_type' => 'manual',
                'notes' => $request->validated('notes'),
                'created_by' => $request->user()->id,
            ]);

            return $change;
        });

        $signedChange = ($change > 0 ? '+' : '').$change;

        return to_route('inventory.show', $product)->with('status', "{$type->label()}: {$signedChange}. Tồn kho hiện tại: {$product->inventory()->value('quantity_on_hand')}.");
    }
}
