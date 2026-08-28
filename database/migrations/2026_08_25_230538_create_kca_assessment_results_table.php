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
        Schema::create('kca_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_enrollment_id')->constrained('kca_enrollments')->restrictOnDelete();
            $table->foreignId('kca_module_id')->nullable()->constrained('kca_modules')->restrictOnDelete();
            $table->foreignId('kca_assignment_id')->nullable()->constrained('kca_assignments')->restrictOnDelete();
            $table->string('assessment_code', 100);
            $table->string('result_code', 100);
            $table->decimal('score', 8, 2)->nullable();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->foreignId('assessed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('assessed_at');
            $table->timestamps();

            $table->unique(
                ['kca_enrollment_id', 'assessment_code', 'attempt_number'],
                'kca_assessment_attempt_unique',
            );
            $table->index(['kca_module_id', 'result_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_assessment_results');
    }
};
