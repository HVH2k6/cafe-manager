<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds are made by hand (cash or a bank transfer done outside the app —
 * payOS cannot send money back), so we record how and by whom.
 * `payments` already has refunded_at / refund_reason / refund_amount.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $usersIdUnsigned = $this->idIsUnsigned('users');

        Schema::table('payments', function (Blueprint $table) use ($usersIdUnsigned) {
            $table->string('refund_method', 20)->nullable()->after('refund_amount');
            $table->string('refund_reference', 100)->nullable()->after('refund_method');
            $table->bigInteger('refunded_by', unsigned: $usersIdUnsigned)->nullable()->after('refund_reference');
            $table->foreign('refunded_by')->references('id')->on('users')->nullOnDelete();
        });

        // A transfer that reached an order settled another way is refunded straight from its link.
        Schema::table('payos_payment_links', function (Blueprint $table) use ($usersIdUnsigned) {
            $table->dateTime('refunded_at')->nullable()->after('paid_at');
            $table->string('refund_method', 20)->nullable()->after('refunded_at');
            $table->string('refund_reference', 100)->nullable()->after('refund_method');
            $table->string('refund_note')->nullable()->after('refund_reference');
            $table->bigInteger('refunded_by', unsigned: $usersIdUnsigned)->nullable()->after('refund_note');
            $table->foreign('refunded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payos_payment_links', function (Blueprint $table) {
            $table->dropForeign(['refunded_by']);
            $table->dropColumn(['refunded_at', 'refund_method', 'refund_reference', 'refund_note', 'refunded_by']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['refunded_by']);
            $table->dropColumn(['refund_method', 'refund_reference', 'refunded_by']);
        });
    }

    /**
     * Tables imported from cafe_schema_final.sql use signed BIGINT ids; a
     * MySQL foreign key must match its target exactly.
     */
    private function idIsUnsigned(string $table): bool
    {
        $type = collect(Schema::getColumns($table))->firstWhere('name', 'id')['type'] ?? '';

        return str_contains(strtolower($type), 'unsigned');
    }
};
