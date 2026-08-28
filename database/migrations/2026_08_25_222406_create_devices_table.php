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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->char('identifier_hash', 64);
            $table->string('label', 100)->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('platform', 100)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 100)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'identifier_hash']);
            $table->index(['user_id', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
