<?php

namespace App;

/**
 * Mirrors the `order_items.status` column in cafe_schema_final.sql.
 * pending → preparing → ready (kitchen) → served (waiter); cancelled any time before preparing.
 */
enum OrderItemStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ làm',
            self::Preparing => 'Đang làm',
            self::Ready => 'Xong',
            self::Served => 'Đã mang ra',
            self::Cancelled => 'Đã hủy',
        };
    }

    /**
     * Tailwind classes: [pill, dot].
     *
     * @return array{0: string, 1: string}
     */
    public function badgeClasses(): array
    {
        return match ($this) {
            self::Pending => ['bg-stone-100 text-stone-700', 'bg-stone-400'],
            self::Preparing => ['bg-amber-50 text-amber-700', 'bg-amber-500'],
            self::Ready => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500'],
            self::Served => ['bg-blue-50 text-blue-700', 'bg-blue-500'],
            self::Cancelled => ['bg-red-50 text-red-700 line-through', 'bg-red-400'],
        };
    }
}
