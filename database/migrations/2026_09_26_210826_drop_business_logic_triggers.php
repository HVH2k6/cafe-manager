<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Business rules (order totals, table status, stock reservation, daily sales)
 * move from the MySQL triggers of cafe_schema_final.sql into Laravel, where
 * they can be tested — the SQLite test database has none of these triggers.
 *
 * down() recreates the original triggers verbatim.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> trigger name => CREATE TRIGGER statement
     */
    private const TRIGGERS = [
        'update_table_status_on_order_create' => <<<'SQL'
            CREATE TRIGGER update_table_status_on_order_create
            AFTER INSERT ON orders
            FOR EACH ROW
            BEGIN
                IF NEW.table_id IS NOT NULL THEN
                    UPDATE tables SET status = 'occupied', updated_at = NOW() WHERE id = NEW.table_id;
                END IF;
            END
            SQL,

        'update_table_status_on_order_complete' => <<<'SQL'
            CREATE TRIGGER update_table_status_on_order_complete
            AFTER UPDATE ON orders
            FOR EACH ROW
            BEGIN
                IF NEW.table_id IS NOT NULL AND NEW.status = 'completed' AND OLD.status != 'completed' THEN
                    UPDATE tables SET status = 'dirty', updated_at = NOW() WHERE id = NEW.table_id;
                END IF;
            END
            SQL,

        'release_reserved_inventory_on_order_cancel' => <<<'SQL'
            CREATE TRIGGER release_reserved_inventory_on_order_cancel
            AFTER UPDATE ON orders
            FOR EACH ROW
            BEGIN
                IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' THEN
                    UPDATE inventory i
                    JOIN order_items oi ON oi.product_id = i.product_id
                    SET i.quantity_reserved = GREATEST(0, i.quantity_reserved - oi.quantity)
                    WHERE oi.order_id = NEW.id;

                    INSERT INTO inventory_transactions (product_id, transaction_type, quantity, reference_id, reference_type, notes)
                    SELECT oi.product_id, 'return', -oi.quantity, NEW.id, 'order', CONCAT('Order cancelled: ', NEW.order_number)
                    FROM order_items oi
                    WHERE oi.order_id = NEW.id;
                END IF;
            END
            SQL,

        'reserve_inventory_on_order_item_create' => <<<'SQL'
            CREATE TRIGGER reserve_inventory_on_order_item_create
            AFTER INSERT ON order_items
            FOR EACH ROW
            BEGIN
                IF NEW.product_id IS NOT NULL THEN
                    UPDATE inventory SET quantity_reserved = quantity_reserved + NEW.quantity WHERE product_id = NEW.product_id;

                    IF (SELECT (quantity_on_hand - quantity_reserved) FROM inventory WHERE product_id = NEW.product_id) < 0 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient inventory for product';
                    END IF;
                END IF;
            END
            SQL,

        'calculate_order_total_on_insert' => <<<'SQL'
            CREATE TRIGGER calculate_order_total_on_insert
            BEFORE INSERT ON orders
            FOR EACH ROW
            BEGIN
                SET NEW.total_amount = COALESCE(NEW.subtotal, 0) - COALESCE(NEW.discount_amount, 0);
            END
            SQL,

        'calculate_order_total_on_update' => <<<'SQL'
            CREATE TRIGGER calculate_order_total_on_update
            BEFORE UPDATE ON orders
            FOR EACH ROW
            BEGIN
                SET NEW.total_amount = COALESCE(NEW.subtotal, 0) - COALESCE(NEW.discount_amount, 0);
            END
            SQL,

        'update_daily_sales_on_order_complete' => <<<'SQL'
            CREATE TRIGGER update_daily_sales_on_order_complete
            AFTER UPDATE ON orders
            FOR EACH ROW
            BEGIN
                IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
                    INSERT INTO daily_sales_summary (date, total_orders, total_revenue, discount_total, average_order_value)
                    VALUES (DATE(NEW.completed_at), 1, NEW.total_amount, NEW.discount_amount, NEW.total_amount)
                    ON DUPLICATE KEY UPDATE
                        total_orders = total_orders + 1,
                        total_revenue = total_revenue + NEW.total_amount,
                        discount_total = discount_total + NEW.discount_amount,
                        average_order_value = total_revenue / total_orders,
                        updated_at = NOW();
                END IF;
            END
            SQL,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_keys(self::TRIGGERS) as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::TRIGGERS as $trigger => $statement) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
            DB::unprepared($statement);
        }
    }
};
