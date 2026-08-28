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
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('key', 191);
            $table->string('environment', 50);
            $table->string('scope_type', 100)->nullable();
            $table->string('scope_key', 64)->nullable();
            $table->char('context_hash', 64);
            $table->boolean('is_enabled')->default(false);
            $table->unsignedTinyInteger('rollout_percentage')->default(100);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['key', 'context_hash']);
            $table->index(['environment', 'scope_type', 'scope_key']);
            $table->index(['is_enabled', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
