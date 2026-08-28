<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('payment_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reason_code', 100);
            $table->string('status', 32);
            $table->char('idempotency_scope_hash', 64)->unique();
            $table->char('payload_fingerprint', 64);
            $table->timestamp('requested_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_transaction_id', 'requested_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
