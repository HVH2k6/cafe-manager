<?php

namespace App;

/**
 * Mirrors the `inventory_transactions.transaction_type` column in cafe_schema_final.sql.
 * Quantities are stored signed: positive adds stock, negative removes it.
 */
enum InventoryTransactionType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Adjustment = 'adjustment';
    case Return = 'return';
    case Damage = 'damage';
    case Expiry = 'expiry';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Nhập hàng',
            self::Sale => 'Bán',
            self::Adjustment => 'Kiểm kê',
            self::Return => 'Trả lại',
            self::Damage => 'Hư hỏng',
            self::Expiry => 'Hết hạn',
        };
    }

    /**
     * Movements staff record by hand; sales and returns come from orders.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Purchase, self::Adjustment, self::Damage, self::Expiry];
    }

    /**
     * Whether the entered quantity is taken out of stock (damage, expiry).
     */
    public function removesStock(): bool
    {
        return in_array($this, [self::Sale, self::Damage, self::Expiry], true);
    }
}
