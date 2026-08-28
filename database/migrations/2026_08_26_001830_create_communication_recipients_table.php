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
        Schema::create('communication_recipients', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('communication_broadcast_id')->constrained('communication_broadcasts')->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20);
            $table->string('reason_code', 100);
            $table->timestamp('resolved_at');
            $table->timestamps();

            $table->unique(
                ['communication_broadcast_id', 'person_id'],
                'communication_recipient_broadcast_person_unique',
            );
            $table->index(['communication_broadcast_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_recipients');
    }
};
