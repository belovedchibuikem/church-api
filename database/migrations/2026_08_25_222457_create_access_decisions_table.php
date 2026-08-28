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
        Schema::create('access_decisions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('matched_role_assignment_id')
                ->nullable()
                ->constrained('role_assignments')
                ->restrictOnDelete();
            $table->string('permission_code', 191);
            $table->string('scope_type', 100);
            $table->string('scope_key', 64);
            $table->boolean('allowed');
            $table->string('reason_code', 100);
            $table->uuid('correlation_id')->nullable();
            $table->timestamp('decided_at');

            $table->index(['actor_user_id', 'decided_at']);
            $table->index(['permission_code', 'decided_at']);
            $table->index(['scope_type', 'scope_key']);
            $table->index(['allowed', 'reason_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_decisions');
    }
};
