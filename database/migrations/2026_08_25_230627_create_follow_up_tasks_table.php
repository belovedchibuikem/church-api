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
        Schema::create('follow_up_tasks', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('first_timer_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to_person_id')
                ->nullable()
                ->constrained('people')
                ->restrictOnDelete();
            $table->string('type', 50);
            $table->string('status', 32)->default('pending');
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('completion_reason_code', 100)->nullable();
            $table->timestamps();

            $table->unique(['first_timer_id', 'type']);
            $table->index(['status', 'due_at']);
            $table->index(['assigned_to_person_id', 'status', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follow_up_tasks');
    }
};
