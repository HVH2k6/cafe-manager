<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors section 3 of cafe_schema_final.sql (categories, products, inventory).
 * Databases imported from that script already have these tables, so each one
 * is only created when missing.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->string('slug', 100)->unique();
                $table->text('description')->nullable();
                $table->string('icon', 100)->nullable();
                $table->integer('display_order')->default(0)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug', 100)->index();
                $table->foreignId('category_id')->constrained();
                $table->text('description')->nullable();
                $table->string('image_url', 500)->nullable();
                $table->decimal('base_price', 10, 2);
                $table->decimal('selling_price', 10, 2);
                $table->decimal('cost_price', 10, 2)->default(0);
                $table->string('sku', 50)->nullable()->unique();
                $table->string('barcode', 100)->nullable();
                $table->boolean('is_available')->default(true)->index();
                $table->boolean('is_featured')->default(false);
                $table->integer('display_order')->default(0);
                $table->integer('prep_time_minutes')->default(5);
                $table->integer('calories')->nullable();
                $table->timestamps();

                $table->index(['category_id', 'is_available']);
            });
        }

        if (! Schema::hasTable('inventory')) {
            Schema::create('inventory', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
                $table->integer('quantity_on_hand')->default(0)->index();
                $table->integer('quantity_reserved')->default(0);
                $table->integer('min_stock')->default(5);
                $table->integer('max_stock')->default(100);
                $table->integer('reorder_level')->default(20);
                $table->string('unit_of_measure', 20)->default('piece');
                $table->dateTime('last_restock')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inventory_transactions')) {
            Schema::create('inventory_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained();
                $table->enum('transaction_type', ['purchase', 'sale', 'adjustment', 'return', 'damage', 'expiry'])->index();
                $table->integer('quantity');
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reference_type', 50)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->timestamp('created_at')->useCurrent()->index();

                $table->index(['reference_type', 'reference_id']);
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
