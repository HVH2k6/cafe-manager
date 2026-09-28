<?php

namespace App;

/**
 * Mirrors the `orders.status` column in cafe_schema_final.sql.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::Preparing => 'Đang chuẩn bị',
            self::Ready => 'Sẵn sàng',
            self::Served => 'Chờ thanh toán',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
        };
    }

    /**
     * Statuses of an order that is still being handled (not completed or cancelled).
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Confirmed, self::Preparing, self::Ready, self::Served];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /**
     * Tailwind classes for the status pill: [pill, dot].
     *
     * @return array{0: string, 1: string}
     */
    public function badgeClasses(): array
    {
        return match ($this) {
            self::Pending, self::Confirmed => ['bg-blue-50 text-blue-700', 'bg-blue-500'],
            self::Preparing => ['bg-amber-50 text-amber-700', 'bg-amber-500'],
            self::Ready, self::Completed => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500'],
            self::Served => ['bg-stone-100 text-latte-dark', 'bg-latte'],
            self::Cancelled => ['bg-red-50 text-red-700', 'bg-red-500'],
        };
    }
}
