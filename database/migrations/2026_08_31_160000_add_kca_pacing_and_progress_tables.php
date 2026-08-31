<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kca_modules', function (Blueprint $table): void {
            if (! Schema::hasColumn('kca_modules', 'duration_days')) {
                $table->unsignedSmallInteger('duration_days')->default(1)->after('sequence');
            }
            if (! Schema::hasColumn('kca_modules', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('is_active');
            }
        });

        Schema::table('kca_lessons', function (Blueprint $table): void {
            if (! Schema::hasColumn('kca_lessons', 'day_index')) {
                $table->unsignedSmallInteger('day_index')->nullable()->after('sequence');
            }
            if (! Schema::hasColumn('kca_lessons', 'lesson_type')) {
                $table->string('lesson_type', 40)->default('text')->after('day_index');
            }
            if (! Schema::hasColumn('kca_lessons', 'requires_acknowledgement')) {
                $table->boolean('requires_acknowledgement')->default(true)->after('lesson_type');
            }
        });

        Schema::table('kca_cohorts', function (Blueprint $table): void {
            if (! Schema::hasColumn('kca_cohorts', 'timezone')) {
                $table->string('timezone', 64)->default('UTC')->after('ends_on');
            }
        });

        if (! Schema::hasTable('kca_lesson_progress')) {
            Schema::create('kca_lesson_progress', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('kca_enrollment_id')->constrained('kca_enrollments')->restrictOnDelete();
                $table->foreignId('kca_lesson_id')->constrained('kca_lessons')->restrictOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('idempotency_key', 80)->nullable();
                $table->timestamps();

                $table->unique(['kca_enrollment_id', 'kca_lesson_id']);
                $table->unique(['kca_enrollment_id', 'idempotency_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kca_lesson_progress');
        Schema::table('kca_cohorts', function (Blueprint $table): void {
            if (Schema::hasColumn('kca_cohorts', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });
        Schema::table('kca_lessons', function (Blueprint $table): void {
            foreach (['requires_acknowledgement', 'lesson_type', 'day_index'] as $column) {
                if (Schema::hasColumn('kca_lessons', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('kca_modules', function (Blueprint $table): void {
            foreach (['published_at', 'duration_days'] as $column) {
                if (Schema::hasColumn('kca_modules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
