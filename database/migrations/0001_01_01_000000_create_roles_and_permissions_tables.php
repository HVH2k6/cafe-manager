<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors section 1 of cafe_schema_final.sql. Databases imported from that
 * script already have these tables, so each one is only created when missing.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('role')) {
            Schema::create('role', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->text('description')->nullable();
                $table->integer('display_order')->default(0)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permission')) {
            Schema::create('permission', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->string('module', 50)->index();
                $table->string('action', 50);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->unique(['module', 'action']);
            });
        }

        if (! Schema::hasTable('role_has_permission')) {
            Schema::create('role_has_permission', function (Blueprint $table) {
                $table->foreignId('role_id')->constrained('role')->cascadeOnDelete();
                $table->foreignId('permission_id')->index()->constrained('permission')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->primary(['role_id', 'permission_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_has_permission');
        Schema::dropIfExists('permission');
        Schema::dropIfExists('role');
    }
};
