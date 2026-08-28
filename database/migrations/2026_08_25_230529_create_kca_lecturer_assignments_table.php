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
        Schema::create('kca_lecturer_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_module_id')->constrained('kca_modules')->restrictOnDelete();
            $table->foreignId('kca_cohort_id')->constrained('kca_cohorts')->restrictOnDelete();
            $table->foreignId('lecturer_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['kca_module_id', 'kca_cohort_id', 'lecturer_person_id'],
                'kca_lecturer_assignment_unique',
            );
            $table->index(['lecturer_person_id', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_lecturer_assignments');
    }
};
