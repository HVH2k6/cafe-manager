<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors section 4 of cafe_schema_final.sql (physical cafe tables). Databases
 * imported from that script already have the table, so it is only created when missing.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('tables')) {
            return;
        }

        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->integer('table_no')->unique();
            $table->integer('capacity')->default(2);
            $table->string('location', 100)->nullable()->index();
            $table->string('section', 50)->nullable()->index();
            $table->enum('status', ['available', 'occupied', 'reserved', 'maintenance', 'dirty'])->default('available')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The table may predate this migration (imported from the SQL script), so it is left in place.
    }
};
