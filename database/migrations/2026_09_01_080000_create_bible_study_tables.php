<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bible_reading_positions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->string('book_id', 8);
            $table->unsignedSmallInteger('chapter');
            $table->timestamps();

            $table->unique('person_id');
        });

        Schema::create('bible_plan_enrollments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->string('plan_code', 16);
            $table->date('started_on');
            $table->string('timezone', 64)->default('UTC');
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->index(['person_id', 'status']);
        });

        Schema::create('bible_plan_day_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('bible_plan_enrollments')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->timestamp('completed_at');

            $table->unique(['enrollment_id', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_plan_day_completions');
        Schema::dropIfExists('bible_plan_enrollments');
        Schema::dropIfExists('bible_reading_positions');
    }
};
