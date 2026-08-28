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
        Schema::create('follow_up_interactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('mission_soul_journey_id')->constrained()->restrictOnDelete();
            $table->foreignId('mentor_assignment_id')->constrained()->restrictOnDelete();
            $table->string('channel_code', 100);
            $table->string('outcome_code', 100);
            $table->char('idempotency_scope_hash', 64)->unique();
            $table->char('payload_fingerprint', 64);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['mission_soul_journey_id', 'occurred_at'], 'mission_soul_follow_up_timeline_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follow_up_interactions');
    }
};
