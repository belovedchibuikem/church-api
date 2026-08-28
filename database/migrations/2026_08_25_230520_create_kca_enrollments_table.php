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
        Schema::create('kca_enrollments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_application_id')->unique()->constrained('kca_applications')->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('kca_year_id')->constrained('kca_years')->restrictOnDelete();
            $table->foreignId('kca_cohort_id')->constrained('kca_cohorts')->restrictOnDelete();
            $table->string('registration_number', 100)->unique();
            $table->date('starts_on');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['person_id', 'kca_year_id']);
            $table->index(['kca_cohort_id', 'starts_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_enrollments');
    }
};
