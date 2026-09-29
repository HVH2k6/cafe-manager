<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 10: cafe settings (key / value) and the activity log.
 * `activity_logs` mirrors cafe_schema_final.sql, so it is only created when
 * missing; `description` (the human-readable line) is added to either version.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('user_id', unsigned: $this->idIsUnsigned('users'))->nullable()->index();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->string('action', 100)->nullable();
                $table->string('entity_type', 50)->nullable();
                $table->bigInteger('entity_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent()->index();

                $table->index(['entity_type', 'entity_id']);
            });
        }

        if (! Schema::hasColumn('activity_logs', 'description')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->string('description', 500)->nullable()->after('entity_id');
            });
        }
    }

    /**
     * Tables imported from cafe_schema_final.sql use signed BIGINT ids.
     */
    private function idIsUnsigned(string $table): bool
    {
        $type = collect(Schema::getColumns($table))->firstWhere('name', 'id')['type'] ?? '';

        return str_contains(strtolower($type), 'unsigned');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');

        if (Schema::hasColumn('activity_logs', 'description')) {
            Schema::table('activity_logs', fn (Blueprint $table) => $table->dropColumn('description'));
        }
        // `activity_logs` may predate this migration (imported from the SQL script), so it is left in place.
    }
};
