<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors section 2 of cafe_schema_final.sql. Databases imported from that
 * script already have the table, so it is only created when missing.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            return;
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('position', 100)->index();
            $table->string('department', 100)->nullable()->index();
            $table->decimal('salary', 10, 2)->default(0);
            $table->date('hire_date');
            $table->enum('contract_type', ['full_time', 'part_time', 'contract', 'intern'])->default('full_time');
            $table->boolean('is_active')->default(true)->index();
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
