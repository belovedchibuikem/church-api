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
        Schema::create('kca_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_enrollment_id')->constrained('kca_enrollments')->restrictOnDelete();
            $table->foreignId('kca_module_id')->constrained('kca_modules')->restrictOnDelete();
            $table->string('title', 191);
            $table->string('state', 40)->default('draft');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('mentor_reviewed_at')->nullable();
            $table->timestamp('admin_reviewed_at')->nullable();
            $table->timestamp('final_assessed_at')->nullable();
            $table->foreignId('last_transitioned_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['kca_enrollment_id', 'state']);
            $table->index(['kca_module_id', 'state']);
            $table->index(['state', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_assignments');
    }
};
