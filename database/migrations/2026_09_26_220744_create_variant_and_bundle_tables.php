<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors product_variants and the product bundle tables of cafe_schema_final.sql.
 * Databases imported from that script already have them, so each one is only
 * created when missing.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('value', 100)->nullable();
                $table->decimal('price_modifier', 10, 2)->default(0);
                $table->boolean('is_default')->default(false);
                $table->integer('display_order')->default(0)->index();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('product_bundles')) {
            Schema::create('product_bundles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug', 100)->unique();
                $table->text('description')->nullable();
                $table->decimal('bundle_price', 10, 2);
                $table->decimal('individual_price', 10, 2)->nullable();
                $table->decimal('discount_amount', 10, 2)->nullable();
                $table->string('image_url', 500)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('display_order')->default(0)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_bundle_items')) {
            Schema::create('product_bundle_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bundle_id')->constrained('product_bundles')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained();
                $table->integer('quantity')->default(1);
                $table->integer('display_order')->default(0);

                $table->unique(['bundle_id', 'product_id']);
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
