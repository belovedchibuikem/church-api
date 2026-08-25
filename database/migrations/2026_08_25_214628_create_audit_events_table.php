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
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 191);
            $table->string('target_type', 100)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->string('scope_type', 100)->nullable();
            $table->string('scope_id', 64)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['action', 'occurred_at']);
            $table->index(['target_type', 'target_id']);
            $table->index(['scope_type', 'scope_id']);
            $table->index('correlation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
