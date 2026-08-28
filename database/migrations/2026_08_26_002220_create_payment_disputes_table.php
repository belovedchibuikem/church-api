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
        Schema::create('payment_disputes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('payment_transaction_id')->constrained()->restrictOnDelete();
            $table->char('provider_event_hash', 64)->unique();
            $table->char('dispute_case_hash', 64)->index();
            $table->string('status', 32);
            $table->string('reason_code', 100);
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_transaction_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_disputes');
    }
};
