<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors section 5 of cafe_schema_final.sql (orders, order_items). Databases
 * imported from that script already have them, so each one is only created
 * when missing. `qr_session_id` has no foreign key here because qr_sessions
 * is not created by the migrations yet (step 9).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 50)->unique();
                $table->foreignId('user_id')->index()->constrained();
                $table->foreignId('table_id')->nullable()->index()->constrained('tables')->nullOnDelete();
                $table->unsignedBigInteger('qr_session_id')->nullable();
                $table->enum('order_type', ['dine_in', 'takeaway', 'delivery'])->default('dine_in')->index();
                $table->enum('status', ['pending', 'confirmed', 'preparing', 'ready', 'served', 'completed', 'cancelled'])->default('pending')->index();
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->string('discount_reason')->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->text('notes')->nullable();
                $table->string('customer_phone', 20)->nullable()->index();
                $table->string('customer_name')->nullable();
                $table->dateTime('estimated_ready_at')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->string('cancelled_reason')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('created_at');
                $table->index(['created_at', 'status']);
            });
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->index()->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->index()->constrained();
                $table->foreignId('bundle_id')->nullable()->index()->constrained('product_bundles');
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 10, 2);
                $table->decimal('subtotal', 12, 2)->storedAs('quantity * unit_price');
                $table->json('variant_ids')->nullable();
                $table->json('variant_values')->nullable();
                $table->text('special_instructions')->nullable();
                $table->enum('status', ['pending', 'preparing', 'ready', 'served', 'cancelled'])->default('pending')->index();
                $table->foreignId('prepared_by')->nullable()->index()->constrained('users')->nullOnDelete();
                $table->dateTime('prepared_at')->nullable();
                $table->dateTime('served_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The tables may predate this migration (imported from the SQL script), so they are left in place.
    }
};
