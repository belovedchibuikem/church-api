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
        Schema::create('mission_soul_journeys', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('crusade_id')->constrained()->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->string('status', 32)->default('new');
            $table->char('capture_idempotency_scope_hash', 64)->nullable()->unique();
            $table->char('capture_payload_fingerprint', 64)->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('mentor_assigned_at')->nullable();
            $table->timestamp('last_follow_up_at')->nullable();
            $table->timestamp('follow_up_completed_at')->nullable();
            $table->string('follow_up_completion_reason_code', 100)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('closure_reason_code', 100)->nullable();
            $table->timestamps();

            $table->unique(['crusade_id', 'person_id'], 'mission_soul_crusade_person_unique');
            $table->index(['crusade_id', 'status']);
            $table->index(['status', 'last_follow_up_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_soul_journeys');
    }
};
