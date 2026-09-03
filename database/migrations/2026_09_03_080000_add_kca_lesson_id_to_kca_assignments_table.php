<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kca_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('kca_assignments', 'kca_lesson_id')) {
                $table->foreignId('kca_lesson_id')
                    ->nullable()
                    ->after('kca_module_id')
                    ->constrained('kca_lessons')
                    ->restrictOnDelete();
                $table->index(['kca_lesson_id', 'state']);
            }
        });

        // Backfill: attach the earliest lesson in each assignment's module when possible.
        if (Schema::hasColumn('kca_assignments', 'kca_lesson_id')) {
            $assignments = DB::table('kca_assignments')->whereNull('kca_lesson_id')->get(['id', 'kca_module_id']);
            foreach ($assignments as $assignment) {
                $lessonId = DB::table('kca_lessons')
                    ->where('kca_module_id', $assignment->kca_module_id)
                    ->orderBy('sequence')
                    ->orderBy('id')
                    ->value('id');
                if ($lessonId) {
                    DB::table('kca_assignments')->where('id', $assignment->id)->update(['kca_lesson_id' => $lessonId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('kca_assignments', function (Blueprint $table): void {
            if (Schema::hasColumn('kca_assignments', 'kca_lesson_id')) {
                $table->dropConstrainedForeignId('kca_lesson_id');
            }
        });
    }
};
