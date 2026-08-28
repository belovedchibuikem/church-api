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
        Schema::create('kca_evidence_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_assignment_id')->constrained('kca_assignments')->restrictOnDelete();
            $table->foreignId('kca_enrollment_id')->constrained('kca_enrollments')->restrictOnDelete();
            $table->foreignId('file_asset_id')->constrained('file_assets')->restrictOnDelete();
            $table->foreignId('submitted_by_person_id')->constrained('people')->restrictOnDelete();
            $table->char('idempotency_key_hash', 64);
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(
                ['kca_assignment_id', 'idempotency_key_hash'],
                'kca_evidence_idempotency_unique',
            );
            $table->unique(['kca_assignment_id', 'file_asset_id']);
            $table->index(['kca_enrollment_id', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_evidence_submissions');
    }
};
