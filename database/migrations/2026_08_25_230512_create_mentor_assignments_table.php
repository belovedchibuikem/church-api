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
        Schema::create('mentor_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('mission_soul_journey_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('mission_team_assignment_id')->constrained()->restrictOnDelete();
            $table->char('idempotency_scope_hash', 64)->nullable()->unique();
            $table->char('payload_fingerprint', 64)->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['mission_team_assignment_id', 'ended_at'], 'mentor_team_assignment_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentor_assignments');
    }
};
