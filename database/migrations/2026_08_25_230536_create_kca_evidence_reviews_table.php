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
        Schema::create('kca_evidence_reviews', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_evidence_submission_id')->unique()->constrained('kca_evidence_submissions')->restrictOnDelete();
            $table->foreignId('reviewer_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('reviewed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('outcome', 40);
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index(['reviewer_person_id', 'reviewed_at']);
            $table->index(['outcome', 'reviewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_evidence_reviews');
    }
};
