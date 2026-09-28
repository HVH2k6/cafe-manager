<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors section 7 of cafe_schema_final.sql (QR ordering). Databases imported
 * from that script already have the tables, so each one is only created when
 * missing. Also:
 *  - orders.user_id becomes nullable: an order a customer sends by QR has no staff creator.
 *  - orders.payment_preference: how the customer said they will pay (cash, card, transfer…).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('qr_operating_hours')) {
            Schema::create('qr_operating_hours', function (Blueprint $table) {
                $table->id();
                $table->enum('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])->unique();
                $table->time('opening_time');
                $table->time('closing_time');
                $table->boolean('is_open')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('qr_codes')) {
            Schema::create('qr_codes', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('table_id', unsigned: $this->idIsUnsigned('tables'))->unique();
                $table->foreign('table_id')->references('id')->on('tables')->cascadeOnDelete();
                $table->string('qr_token')->unique();
                $table->longText('qr_data')->nullable();
                $table->string('qr_url', 500);
                $table->boolean('is_active')->default(true)->index();
                $table->string('disabled_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('qr_sessions')) {
            Schema::create('qr_sessions', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('table_id', unsigned: $this->idIsUnsigned('tables'))->index();
                $table->foreign('table_id')->references('id')->on('tables')->cascadeOnDelete();
                $table->string('qr_token');
                $table->string('session_token')->unique();
                $table->bigInteger('order_id', unsigned: $this->idIsUnsigned('orders'))->nullable()->index();
                $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
                $table->enum('status', ['active', 'completed', 'abandoned', 'expired'])->default('active')->index();
                $table->json('cart_data')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->dateTime('last_activity')->nullable();
                $table->dateTime('expires_at')->nullable()->index();
                $table->timestamp('created_at')->nullable()->useCurrent()->index();
                $table->dateTime('completed_at')->nullable();
            });
        }

        if (! Schema::hasTable('qr_notifications')) {
            Schema::create('qr_notifications', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('qr_session_id', unsigned: $this->idIsUnsigned('qr_sessions'))->index();
                $table->foreign('qr_session_id')->references('id')->on('qr_sessions')->cascadeOnDelete();
                $table->bigInteger('order_id', unsigned: $this->idIsUnsigned('orders'))->nullable();
                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
                $table->enum('type', ['order_received', 'order_preparing', 'order_ready', 'call_staff', 'payment_link'])->default('order_received');
                $table->string('title')->nullable();
                $table->text('message')->nullable();
                $table->string('action_url', 500)->nullable();
                $table->boolean('is_read')->default(false)->index();
                $table->boolean('is_sent')->default(true);
                $table->dateTime('read_at')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent()->index();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->bigInteger('user_id', unsigned: $this->idIsUnsigned('users'))->nullable()->change();
        });

        if (! Schema::hasColumn('orders', 'payment_preference')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('payment_preference', 20)->nullable()->after('customer_name');
            });
        }
    }

    /**
     * Tables imported from cafe_schema_final.sql use signed BIGINT ids while
     * Laravel's own migrations use unsigned ones; MySQL needs a foreign key
     * to match its target exactly.
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
        if (Schema::hasColumn('orders', 'payment_preference')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('payment_preference'));
        }
        // The QR tables may predate this migration (imported from the SQL script), so they are left in place,
        // and orders.user_id stays nullable (orders sent by QR have none).
    }
};
