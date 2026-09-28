<?php

namespace App;

/**
 * Mirrors the `orders.order_type` column in cafe_schema_final.sql.
 */
enum OrderType: string
{
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Tại bàn',
            self::Takeaway => 'Mang về',
            self::Delivery => 'Giao hàng',
        };
    }
}
