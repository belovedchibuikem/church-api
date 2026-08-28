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
        Schema::create('first_timers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('person_id')->constrained()->restrictOnDelete();
            $table->foreignId('church_id')->constrained()->restrictOnDelete();
            $table->foreignId('home_church_id')
                ->nullable()
                ->constrained('home_churches')
                ->restrictOnDelete();
            $table->timestamp('registered_at');
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'church_id']);
            $table->index(['church_id', 'registered_at']);
            $table->index(['home_church_id', 'registered_at']);
            $table->index(['contacted_at', 'registered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('first_timers');
    }
};
