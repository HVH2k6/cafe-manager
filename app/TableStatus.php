<?php

namespace App;

/**
 * Mirrors the `tables.status` column in cafe_schema_final.sql.
 */
enum TableStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Reserved = 'reserved';
    case Dirty = 'dirty';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Trống',
            self::Occupied => 'Có khách',
            self::Reserved => 'Đã đặt trước',
            self::Dirty => 'Cần dọn',
            self::Maintenance => 'Bảo trì',
        };
    }

    /**
     * "Occupied" is set by orders only (step 5), never picked by hand.
     *
     * @return list<self>
     */
    public static function manuallySettable(): array
    {
        return [self::Available, self::Reserved, self::Dirty, self::Maintenance];
    }

    /**
     * Tailwind classes: [card, dot].
     *
     * @return array{0: string, 1: string}
     */
    public function classes(): array
    {
        return match ($this) {
            self::Available => ['border-emerald-200 bg-emerald-50/60', 'bg-emerald-500'],
            self::Occupied => ['border-latte/40 bg-cream/60', 'bg-latte'],
            self::Reserved => ['border-blue-200 bg-blue-50/60', 'bg-blue-500'],
            self::Dirty => ['border-amber-200 bg-amber-50/70', 'bg-amber-500'],
            self::Maintenance => ['border-stone-200 bg-stone-100', 'bg-stone-400'],
        };
    }
}
