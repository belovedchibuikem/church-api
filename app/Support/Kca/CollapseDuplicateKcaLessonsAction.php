<?php

namespace App\Support\Kca;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the earliest lesson when the same module stores the same title more than once.
 * Later copies are removed after their attendance, assignments, and chapters point at the kept lesson.
 */
final class CollapseDuplicateKcaLessonsAction
{
    public function handle(): int
    {
        if (! Schema::hasTable('kca_lessons')) {
            return 0;
        }

        $removed = 0;
        $groups = DB::table('kca_lessons')
            ->select('kca_module_id', DB::raw('lower(trim(title)) as title_key'), DB::raw('min(id) as keep_id'))
            ->groupBy('kca_module_id', DB::raw('lower(trim(title))'))
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $duplicateIds = DB::table('kca_lessons')
                ->where('kca_module_id', $group->kca_module_id)
                ->whereRaw('lower(trim(title)) = ?', [$group->title_key])
                ->where('id', '!=', $group->keep_id)
                ->pluck('id');

            foreach ($duplicateIds as $duplicateId) {
                $this->repoint((int) $group->keep_id, (int) $duplicateId);
                DB::table('kca_lessons')->where('id', $duplicateId)->delete();
                $removed++;
            }
        }

        return $removed;
    }

    private function repoint(int $keepId, int $duplicateId): void
    {
        $this->repointUnique('kca_attendances', $keepId, $duplicateId, ['kca_enrollment_id', 'session_on']);
        $this->repointColumn('kca_assignments', $keepId, $duplicateId);
        $this->repointUnique('kca_lecturer_assignments', $keepId, $duplicateId, ['kca_cohort_id', 'lecturer_person_id']);
        $this->repointChapters($keepId, $duplicateId);
        $this->repointUnique('kca_lesson_progress', $keepId, $duplicateId, ['kca_enrollment_id']);
        $this->repointColumn('kca_study_notes', $keepId, $duplicateId);
    }

    /** @param  list<string>  $conflictColumns */
    private function repointUnique(string $table, int $keepId, int $duplicateId, array $conflictColumns): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'kca_lesson_id')) {
            return;
        }

        $rows = DB::table($table)->where('kca_lesson_id', $duplicateId)->get();
        foreach ($rows as $row) {
            $conflict = DB::table($table)->where('kca_lesson_id', $keepId);
            foreach ($conflictColumns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                $conflict->where($column, $row->{$column});
            }
            if ($conflict->exists()) {
                DB::table($table)->where('id', $row->id)->delete();
                continue;
            }
            DB::table($table)->where('id', $row->id)->update(['kca_lesson_id' => $keepId]);
        }
    }

    private function repointChapters(int $keepId, int $duplicateId): void
    {
        if (! Schema::hasTable('kca_chapters')) {
            return;
        }

        $rows = DB::table('kca_chapters')->where('kca_lesson_id', $duplicateId)->get();
        foreach ($rows as $row) {
            $conflict = DB::table('kca_chapters')
                ->where('kca_lesson_id', $keepId)
                ->where(function ($query) use ($row): void {
                    $query->where('code', $row->code)->orWhere('sequence', $row->sequence);
                })
                ->exists();
            if ($conflict) {
                DB::table('kca_chapters')->where('id', $row->id)->delete();
                continue;
            }
            DB::table('kca_chapters')->where('id', $row->id)->update(['kca_lesson_id' => $keepId]);
        }
    }

    private function repointColumn(string $table, int $keepId, int $duplicateId): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'kca_lesson_id')) {
            return;
        }

        DB::table($table)->where('kca_lesson_id', $duplicateId)->update(['kca_lesson_id' => $keepId]);
    }
}
