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
        Schema::create('mission_invitations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('crusade_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('requester_person_id')->nullable()->constrained('people')->restrictOnDelete();
            $table->foreignId('requested_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('status', 32)->default('received');
            $table->string('transition_reason_code', 100)->nullable();
            $table->timestamp('status_changed_at');
            $table->timestamps();

            $table->index(['status', 'status_changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_invitations');
    }
};
