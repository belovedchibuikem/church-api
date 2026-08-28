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
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('payer_person_id')->nullable()->constrained('people')->restrictOnDelete();
            $table->foreignId('event_registration_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('purpose_code', 100);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32);
            $table->char('idempotency_scope_hash', 64)->unique();
            $table->char('payload_fingerprint', 64);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['purpose_code', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};
