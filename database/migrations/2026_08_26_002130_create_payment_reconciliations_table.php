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
        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('payment_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->string('reason_code', 100);
            $table->timestamp('reconciled_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'reconciled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliations');
    }
};
