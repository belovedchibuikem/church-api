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
        Schema::create('mobile_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('security_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->restrictOnDelete();
            $table->ulid('family_id');
            $table->char('token_hash', 64)->unique();
            $table->foreignId('replaced_by_id')->nullable()->constrained('mobile_refresh_tokens')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 100)->nullable();
            $table->timestamps();

            $table->index(['security_session_id', 'revoked_at', 'expires_at'], 'mobile_refresh_session_state_idx');
            $table->index(['family_id', 'revoked_at'], 'mobile_refresh_family_state_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_refresh_tokens');
    }
};
