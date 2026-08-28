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
        Schema::create('kca_attendances', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('kca_enrollment_id')->constrained('kca_enrollments')->restrictOnDelete();
            $table->foreignId('kca_lesson_id')->constrained('kca_lessons')->restrictOnDelete();
            $table->string('status', 20);
            $table->date('session_on');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(
                ['kca_enrollment_id', 'kca_lesson_id', 'session_on'],
                'kca_attendance_session_unique',
            );
            $table->index(['kca_lesson_id', 'session_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kca_attendances');
    }
};
