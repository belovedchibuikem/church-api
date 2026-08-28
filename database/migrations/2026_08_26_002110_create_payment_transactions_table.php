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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('payment_intent_id')->constrained()->restrictOnDelete();
            $table->string('provider_code', 100);
            $table->char('provider_event_hash', 64)->unique();
            $table->char('provider_reference_hash', 64)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_intent_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
