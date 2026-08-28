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
        Schema::create('home_church_application_transitions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('home_church_application_id')
                ->constrained(indexName: 'home_church_application_transition_parent_fk')
                ->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('reason_code', 100);
            $table->uuid('correlation_id')->nullable();
            $table->timestamp('occurred_at');

            $table->index(
                ['home_church_application_id', 'occurred_at'],
                'home_church_application_timeline_index',
            );
            $table->index(
                ['actor_user_id', 'occurred_at'],
                'home_church_application_actor_timeline_index',
            );
            $table->index(['to_status', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_church_application_transitions');
    }
};
