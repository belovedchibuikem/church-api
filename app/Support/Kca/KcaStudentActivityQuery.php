<?php

namespace App\Support\Kca;

use App\Kca\KcaAssignmentState;
use App\Models\KcaAssignment;
use App\Models\KcaAttendance;
use App\Models\KcaChapter;
use App\Models\KcaChapterProgress;
use App\Models\KcaDevotionalReading;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\KcaLessonProgress;
use App\Models\KcaModule;
use App\Models\KcaStudyNote;
use App\Models\Person;
use App\Support\Bible\BibleProgressService;
use App\Support\Identity\PersonDisplayName;

class KcaStudentActivityQuery
{
    public function __construct(
        private BibleProgressService $bible,
        private KcaSoulTreeService $soulTree,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(KcaEnrollment $enrollment): array
    {
        $enrollment->loadMissing('person');
        $modules = KcaModule::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->with(['lessons.chapters'])
            ->orderBy('sequence')
            ->get();
        $lessonIds = $modules->flatMap(fn (KcaModule $module) => $module->lessons->pluck('id'))->all();
        $chapterIds = $modules->flatMap(fn (KcaModule $module) => $module->lessons->flatMap(fn (KcaLesson $lesson) => $lesson->chapters->pluck('id')))->all();
        $completedLessons = KcaLessonProgress::query()
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->whereNotNull('completed_at')
            ->whereIn('kca_lesson_id', $lessonIds ?: [0])
            ->pluck('kca_lesson_id')
            ->all();
        $completedChapters = KcaChapterProgress::query()
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->whereNotNull('completed_at')
            ->whereIn('kca_chapter_id', $chapterIds ?: [0])
            ->pluck('kca_chapter_id')
            ->all();
        $completedLessonSet = array_flip($completedLessons);
        $completedChapterSet = array_flip($completedChapters);

        $curriculum = $modules->map(function (KcaModule $module) use ($completedLessonSet, $completedChapterSet): array {
            $lessons = $module->lessons->map(function (KcaLesson $lesson) use ($completedLessonSet, $completedChapterSet): array {
                $chapters = $lesson->chapters->map(fn (KcaChapter $chapter): array => [
                    'id' => $chapter->public_id,
                    'code' => $chapter->code,
                    'title' => $chapter->title,
                    'sequence' => $chapter->sequence,
                    'completed' => isset($completedChapterSet[$chapter->getKey()]),
                ])->values()->all();
                $chapterTotal = count($chapters);
                $chapterDone = count(array_filter($chapters, fn (array $row): bool => $row['completed']));

                return [
                    'id' => $lesson->public_id,
                    'code' => $lesson->code,
                    'title' => $lesson->title,
                    'sequence' => $lesson->sequence,
                    'completed' => isset($completedLessonSet[$lesson->getKey()]),
                    'chapters_total' => $chapterTotal,
                    'chapters_completed' => $chapterDone,
                    'chapters' => $chapters,
                ];
            })->values()->all();
            $lessonTotal = count($lessons);
            $lessonDone = count(array_filter($lessons, fn (array $row): bool => $row['completed']));

            return [
                'id' => $module->public_id,
                'code' => $module->code,
                'title' => $module->title,
                'sequence' => $module->sequence,
                'lessons_total' => $lessonTotal,
                'lessons_completed' => $lessonDone,
                'percent' => $lessonTotal === 0 ? 0 : (int) round(($lessonDone / $lessonTotal) * 100),
                'lessons' => $lessons,
            ];
        })->values()->all();

        $lessonTotal = count($lessonIds);
        $chapterTotal = count($chapterIds);
        $assignments = KcaAssignment::query()
            ->with([
                'module:id,public_id,code,title,sequence',
                'lesson:id,public_id,code,title,sequence,kca_module_id',
            ])
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->where('state', '!=', KcaAssignmentState::Draft->value)
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->get();

        $notes = KcaStudyNote::query()
            ->with(['lesson:id,public_id', 'chapter:id,public_id'])
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->latest('updated_at')
            ->limit(20)
            ->get();
        $devotionals = KcaDevotionalReading::query()
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->latest('read_at')
            ->limit(20)
            ->get();

        $person = $enrollment->person;

        return [
            'person' => $person ? [
                'id' => $person->public_id,
                'name' => PersonDisplayName::of($person),
            ] : null,
            'curriculum' => [
                'modules' => $curriculum,
                'modules_total' => count($curriculum),
                'modules_completed' => count(array_filter($curriculum, fn (array $row): bool => $row['lessons_total'] > 0 && $row['lessons_completed'] >= $row['lessons_total'])),
                'lessons_total' => $lessonTotal,
                'lessons_completed' => count($completedLessons),
                'chapters_total' => $chapterTotal,
                'chapters_completed' => count($completedChapters),
                'percent' => $lessonTotal === 0 ? 0 : (int) round((count($completedLessons) / $lessonTotal) * 100),
            ],
            'bible' => $person ? $this->bible->snapshot($person) : null,
            'devotionals' => [
                'count' => KcaDevotionalReading::query()->where('kca_enrollment_id', $enrollment->getKey())->count(),
                'recent' => $devotionals->map(fn (KcaDevotionalReading $row): array => [
                    'id' => $row->public_id,
                    'title' => $row->title,
                    'source' => $row->source,
                    'publication_id' => $row->publication_id,
                    'reflection' => $row->reflection,
                    'read_at' => $row->read_at?->utc()->toIso8601String(),
                ])->values()->all(),
            ],
            'notes' => [
                'count' => KcaStudyNote::query()->where('kca_enrollment_id', $enrollment->getKey())->count(),
                'recent' => $notes->map(fn (KcaStudyNote $row): array => [
                    'id' => $row->public_id,
                    'title' => $row->title,
                    'body' => $row->body,
                    'lesson_id' => $row->lesson?->public_id,
                    'chapter_id' => $row->chapter?->public_id,
                    'updated_at' => $row->updated_at?->utc()->toIso8601String(),
                ])->values()->all(),
            ],
            'assignments' => [
                'open' => $assignments->filter(function (KcaAssignment $row): bool {
                    $state = $row->state instanceof KcaAssignmentState ? $row->state : KcaAssignmentState::from((string) $row->state);

                    return ! in_array($state, [KcaAssignmentState::Approved, KcaAssignmentState::FinalAssessment], true);
                })->count(),
                'items' => $assignments->map(fn (KcaAssignment $assignment): array => $this->assignmentPayload($assignment))->values()->all(),
            ],
            'attendance_recorded' => KcaAttendance::query()->where('kca_enrollment_id', $enrollment->getKey())->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assignmentPayload(KcaAssignment $assignment): array
    {
        $state = $assignment->state instanceof KcaAssignmentState ? $assignment->state->value : (string) $assignment->state;
        $payload = [
            'id' => $assignment->public_id,
            'title' => $assignment->title,
            'state' => $state,
            'assignment_kind' => $assignment->assignment_kind ?? 'standard',
            'due_at' => $assignment->due_at?->toIso8601String(),
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'submitted_at' => $assignment->submitted_at?->toIso8601String(),
            'module' => $assignment->module ? [
                'id' => $assignment->module->public_id,
                'code' => $assignment->module->code,
                'title' => $assignment->module->title,
                'sequence' => $assignment->module->sequence,
            ] : null,
            'lesson' => $assignment->lesson ? [
                'id' => $assignment->lesson->public_id,
                'code' => $assignment->lesson->code,
                'title' => $assignment->lesson->title,
                'sequence' => $assignment->lesson->sequence,
            ] : null,
        ];
        if ($assignment->isSoulWinning()) {
            $payload['soul_tree'] = $this->soulTree->progress($assignment);
        }

        return $payload;
    }
}
