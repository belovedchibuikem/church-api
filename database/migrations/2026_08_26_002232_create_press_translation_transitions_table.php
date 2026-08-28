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
        Schema::create('press_translation_transitions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('press_translation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('reason_code', 100);
            $table->uuid('correlation_id')->nullable();
            $table->timestamp('occurred_at');

            $table->index(
                ['press_translation_id', 'occurred_at'],
                'press_translation_transition_timeline_index',
            );
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index('correlation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('press_translation_transitions');
    }
};
