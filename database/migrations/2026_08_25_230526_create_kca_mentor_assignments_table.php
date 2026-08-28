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
        Schema::create('kca_mentor_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_enrollment_id')->constrained('kca_enrollments')->restrictOnDelete();
            $table->foreignId('mentor_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['kca_enrollment_id', 'mentor_person_id', 'starts_at'],
                'kca_mentor_assignment_unique',
            );
            $table->index(['kca_enrollment_id', 'ends_at']);
            $table->index(['mentor_person_id', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_mentor_assignments');
    }
};
