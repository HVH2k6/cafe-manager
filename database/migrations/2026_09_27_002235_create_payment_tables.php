<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `payments` mirrors section 6 of cafe_schema_final.sql (one final payment per
 * order) and is only created when missing. `payos_payment_links` is new: one
 * row per payOS payment link, because payOS never accepts an orderCode twice
 * and an expired or cancelled link has to be replaced by a new one.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->enum('method', ['cash', 'card', 'e_wallet', 'bank_transfer', 'qr_pay'])->default('cash')->index();
                $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'partial'])->default('pending')->index();
                $table->string('transaction_id')->nullable()->unique();
                $table->string('payment_gateway', 100)->nullable();
                $table->json('gateway_response')->nullable();
                $table->decimal('paid_amount', 12, 2)->nullable();
                $table->decimal('change_amount', 10, 2)->default(0);
                $table->string('card_last4', 4)->nullable();
                $table->string('reference_number', 100)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('processed_by')->nullable()->index()->constrained('users')->nullOnDelete();
                $table->dateTime('paid_at')->nullable()->index();
                $table->dateTime('refunded_at')->nullable();
                $table->string('refund_reason')->nullable();
                $table->decimal('refund_amount', 12, 2)->nullable();
                $table->timestamps();
            });
        }

        Schema::create('payos_payment_links', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id', unsigned: $this->idIsUnsigned('orders'));
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('order_code')->unique();
            $table->string('payment_link_id', 100)->nullable()->index();
            $table->unsignedInteger('amount');
            $table->string('description', 25);
            $table->string('bin', 20)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('account_name')->nullable();
            $table->string('checkout_url', 500)->nullable();
            $table->text('qr_code')->nullable();
            $table->string('status', 20)->default('PENDING')->index();
            $table->unsignedInteger('amount_paid')->default(0);
            $table->string('reference', 100)->nullable();
            $table->json('transactions')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->bigInteger('created_by', unsigned: $this->idIsUnsigned('users'))->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });
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
        Schema::dropIfExists('payos_payment_links');
        // `payments` may predate this migration (imported from the SQL script), so it is left in place.
    }
};
