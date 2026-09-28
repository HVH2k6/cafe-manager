<?php

namespace App\Actions\Orders;

use App\Events\OrderCreated;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\OrderStatus;
use App\OrderType;
use App\TableStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates an order with its items in one transaction: numbers it, prices it,
 * holds stock and seats the table.
 */
class PlaceOrder
{
    private const NUMBER_ATTEMPTS = 3;

    public function __construct(
        private BuildOrderItems $buildOrderItems,
        private StockReservation $stock,
    ) {}

    /**
     * @param  User|null  $staff  null for an order a customer sends by QR
     * @param  array{order_type: string, table_id?: ?int, customer_name?: ?string, customer_phone?: ?string, notes?: ?string, qr_session_id?: ?int, payment_preference?: ?string}  $details
     * @param  list<array<string, mixed>>  $lines
     * @param  OrderStatus  $status  Confirmed for staff orders; pending for orders customers send by QR.
     *
     * @throws ValidationException
     */
    public function handle(?User $staff, array $details, array $lines, OrderStatus $status = OrderStatus::Confirmed): Order
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): Order => $this->place($staff, $details, $lines, $status));
            } catch (UniqueConstraintViolationException $exception) {
                // Another order took the same number at the same moment; take the next one.
                if ($attempt === self::NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  list<array<string, mixed>>  $lines
     */
    private function place(?User $staff, array $details, array $lines, OrderStatus $status): Order
    {
        $type = OrderType::from($details['order_type']);
        $table = $type === OrderType::DineIn ? $this->lockFreeTable((int) $details['table_id']) : null;

        $items = $this->buildOrderItems->handle($lines);
        $this->stock->reserve($items);

        $longestPrep = $items->max(fn (OrderItem $item): int => $item->product?->prep_time_minutes ?? 5);

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'user_id' => $staff?->id,
            'qr_session_id' => $details['qr_session_id'] ?? null,
            'table_id' => $table?->id,
            'order_type' => $type,
            'status' => $status,
            'customer_name' => $details['customer_name'] ?? null,
            'customer_phone' => $details['customer_phone'] ?? null,
            'payment_preference' => $details['payment_preference'] ?? null,
            'notes' => $details['notes'] ?? null,
            'estimated_ready_at' => now()->addMinutes($longestPrep),
        ]);

        $order->items()->saveMany($items);
        $order->recalculateTotals();

        $table?->update(['status' => TableStatus::Occupied]);

        // Sent once the transaction commits (and not at all if it rolls back).
        OrderCreated::send($order);

        return $order;
    }

    /**
     * @throws ValidationException
     */
    private function lockFreeTable(int $tableId): DiningTable
    {
        $table = DiningTable::lockForUpdate()->findOrFail($tableId);

        $openOrder = Order::open()->where('table_id', $table->id)->first();

        if ($openOrder) {
            throw ValidationException::withMessages([
                'table_id' => "Bàn {$table->table_no} đang có đơn {$openOrder->order_number}. Hãy thêm món vào đơn đó.",
            ]);
        }

        if ($table->status === TableStatus::Maintenance) {
            throw ValidationException::withMessages(['table_id' => "Bàn {$table->table_no} đang bảo trì."]);
        }

        return $table;
    }
}
