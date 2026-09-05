<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kca_lecturer_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('kca_lecturer_assignments', 'kca_lesson_id')) {
                $table->foreignId('kca_lesson_id')
                    ->nullable()
                    ->after('kca_module_id')
                    ->constrained('kca_lessons')
                    ->restrictOnDelete();
            }
        });

        $this->ensureStandaloneForeignKeyIndexes();

        Schema::table('kca_lecturer_assignments', function (Blueprint $table) {
            $indexNames = collect(Schema::getIndexes('kca_lecturer_assignments'))->pluck('name');
            if ($indexNames->contains('kca_lecturer_assignment_unique')) {
                $table->dropUnique('kca_lecturer_assignment_unique');
            }
            if (! $indexNames->contains('kca_lecturer_lesson_unique')) {
                $table->unique(
                    ['kca_lesson_id', 'kca_cohort_id', 'lecturer_person_id'],
                    'kca_lecturer_lesson_unique',
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('kca_lecturer_assignments', function (Blueprint $table) {
            $indexNames = collect(Schema::getIndexes('kca_lecturer_assignments'))->pluck('name');
            if ($indexNames->contains('kca_lecturer_lesson_unique')) {
                $table->dropUnique('kca_lecturer_lesson_unique');
            }
            if (! $indexNames->contains('kca_lecturer_assignment_unique')) {
                $table->unique(
                    ['kca_module_id', 'kca_cohort_id', 'lecturer_person_id'],
                    'kca_lecturer_assignment_unique',
                );
            }
            if (Schema::hasColumn('kca_lecturer_assignments', 'kca_lesson_id')) {
                $table->dropConstrainedForeignId('kca_lesson_id');
            }
        });
    }

    private function ensureStandaloneForeignKeyIndexes(): void
    {
        $indexNames = collect(Schema::getIndexes('kca_lecturer_assignments'))->pluck('name');

        Schema::table('kca_lecturer_assignments', function (Blueprint $table) use ($indexNames) {
            if (! $indexNames->contains('kca_lecturer_assignments_kca_module_id_index')
                && ! $indexNames->contains('kca_lecturer_assignments_kca_module_id_foreign')) {
                $table->index('kca_module_id', 'kca_lecturer_assignments_kca_module_id_index');
            }
            if (! $indexNames->contains('kca_lecturer_assignments_kca_cohort_id_index')
                && ! $indexNames->contains('kca_lecturer_assignments_kca_cohort_id_foreign')) {
                $table->index('kca_cohort_id', 'kca_lecturer_assignments_kca_cohort_id_index');
            }
            if (! $indexNames->contains('kca_lecturer_assignments_lecturer_person_id_index')
                && ! $indexNames->contains('kca_lecturer_assignments_lecturer_person_id_foreign')) {
                $table->index('lecturer_person_id', 'kca_lecturer_assignments_lecturer_person_id_index');
            }
        });
    }
};
