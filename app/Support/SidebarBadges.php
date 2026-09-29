<?php

namespace App\Support;

use App\Models\DiningTable;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\OrderItemStatus;
use App\OrderStatus;
use App\TableStatus;

/**
 * Counts shown next to the sidebar links, only those the user may see:
 *
 *  - pending    orders waiting for confirmation (Đơn hàng, orders.view)
 *  - kitchen    items still to make on the kitchen display (Màn hình bếp, kitchen.view)
 *  - dirty      tables waiting to be cleaned (Sơ đồ bàn, tables.view)
 *  - low_stock  products below their minimum stock (Tồn kho, inventory.view)
 */
class SidebarBadges
{
    /**
     * Channel each badge listens to for a recount => badges it covers.
     */
    public const CHANNELS = [
        'orders' => 'orders.view',
        'kitchen' => 'kitchen.view',
        'tables' => 'tables.view',
    ];

    /**
     * @return array<string, int>
     */
    public static function for(User $user): array
    {
        return array_filter([
            'pending' => $user->can('orders.view') ? Order::where('status', OrderStatus::Pending)->count() : null,
            'kitchen' => $user->can('kitchen.view') ? self::kitchenCount() : null,
            'dirty' => $user->can('tables.view') ? DiningTable::where('status', TableStatus::Dirty)->count() : null,
            'low_stock' => $user->can('inventory.view') ? Inventory::lowStockCount() : null,
        ], fn (?int $count): bool => $count !== null);
    }

    /**
     * Private channels the user may listen to for badge changes (the others' badges are polled).
     *
     * @return list<string>
     */
    public static function channelsFor(User $user): array
    {
        return array_keys(array_filter(self::CHANNELS, fn (string $permission): bool => $user->can($permission)));
    }

    /**
     * Same items the kitchen display counts: waiting or being made, on confirmed orders.
     */
    private static function kitchenCount(): int
    {
        return OrderItem::whereIn('status', [OrderItemStatus::Pending, OrderItemStatus::Preparing])
            ->whereHas('order', fn ($query) => $query->whereIn('status', [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready]))
            ->count();
    }
}
