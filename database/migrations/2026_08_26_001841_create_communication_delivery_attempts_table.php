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
        Schema::create('communication_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('communication_recipient_id')
                ->constrained(
                    table: 'communication_recipients',
                    indexName: 'communication_delivery_attempt_recipient_fk',
                )
                ->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->string('result_code', 100)->nullable();
            $table->char('idempotency_key_hash', 64);
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['communication_recipient_id', 'idempotency_key_hash'],
                'communication_delivery_attempt_idempotency_unique',
            );
            $table->index(['status', 'attempted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_delivery_attempts');
    }
};
