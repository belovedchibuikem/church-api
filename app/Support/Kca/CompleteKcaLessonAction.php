<?php

namespace App\Support\Kca;

use App\Exceptions\KcaIdempotencyConflictException;
use App\Models\KcaChapter;
use App\Models\KcaChapterProgress;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\KcaLessonProgress;
use App\Models\KcaModule;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CompleteKcaLessonAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        Person $person,
        KcaLesson $lesson,
        bool $acknowledged,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?string $unlockToken = null,
    ): KcaLessonProgress {
        $enrollment = KcaEnrollment::query()
            ->with('cohort')
            ->where('person_id', $person->getKey())
            ->latest('starts_on')
            ->first();
        if ($enrollment === null) {
            throw new AccessDeniedHttpException('Only activated KCA students can complete lessons.');
        }

        return DB::transaction(function () use ($enrollment, $lesson, $acknowledged, $idempotencyKey, $actor, $unlockToken): KcaLessonProgress {
            $lockedLesson = KcaLesson::query()->with('module')->lockForUpdate()->findOrFail($lesson->getKey());
            $module = $lockedLesson->module;
            if ($module === null || $module->published_at === null) {
                throw new InvalidArgumentException('Unpublished modules cannot receive lesson completions.');
            }
            if ($lockedLesson->requires_acknowledgement && ! $acknowledged) {
                throw new InvalidArgumentException('This lesson requires an explicit completion acknowledgement.');
            }

            $existing = KcaLessonProgress::query()
                ->where('kca_enrollment_id', $enrollment->getKey())
                ->where('kca_lesson_id', $lockedLesson->getKey())
                ->lockForUpdate()
                ->first();
            if ($existing?->completed_at !== null) {
                return $existing;
            }
            $this->assertChaptersComplete($enrollment, $lockedLesson);
            if ($idempotencyKey) {
                $byKey = KcaLessonProgress::query()
                    ->where('kca_enrollment_id', $enrollment->getKey())
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($byKey !== null) {
                    if ((int) $byKey->kca_lesson_id !== (int) $lockedLesson->getKey()) {
                        throw new KcaIdempotencyConflictException;
                    }

                    return $byKey;
                }
            }

            $tokenOk = app(KcaLessonUnlockToken::class)->matches($enrollment, $lockedLesson, $unlockToken);
            if (! $tokenOk) {
                $this->assertDayUnlocked($enrollment, $module, (int) $lockedLesson->day_index);
            }

            $now = now()->utc();
            $progress = $existing ?? new KcaLessonProgress;
            $progress->forceFill([
                'kca_enrollment_id' => $enrollment->getKey(),
                'kca_lesson_id' => $lockedLesson->getKey(),
                'started_at' => $progress->started_at ?? $now,
                'completed_at' => $now,
                'idempotency_key' => $idempotencyKey,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.lesson.completed',
                actor: $actor,
                targetType: 'kca_lesson',
                targetId: $lockedLesson->public_id,
                metadata: [
                    'enrollment_id' => $enrollment->public_id,
                    'day_index' => $lockedLesson->day_index,
                ],
            ));

            return $progress;
        }, attempts: 3);
    }

    public function assertAccessible(KcaEnrollment $enrollment, KcaLesson $lesson): void
    {
        $module = $lesson->module ?? KcaModule::query()->find($lesson->kca_module_id);
        if ($module === null || $module->published_at === null) {
            throw new AccessDeniedHttpException('This lesson is not published.');
        }
        $this->assertDayUnlocked($enrollment, $module, (int) ($lesson->day_index ?? 1));
    }

    private function assertChaptersComplete(KcaEnrollment $enrollment, KcaLesson $lesson): void
    {
        $chapterIds = KcaChapter::query()->where('kca_lesson_id', $lesson->getKey())->pluck('id');
        if ($chapterIds->isEmpty()) {
            return;
        }
        $completed = KcaChapterProgress::query()
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->whereIn('kca_chapter_id', $chapterIds)
            ->whereNotNull('completed_at')
            ->count();
        if ($completed < $chapterIds->count()) {
            throw new InvalidArgumentException('Complete every chapter in this lesson before marking the lesson complete.');
        }
    }

    private function assertDayUnlocked(KcaEnrollment $enrollment, KcaModule $module, int $dayIndex): void
    {
        if ($dayIndex < 1) {
            throw new AccessDeniedHttpException('This lesson is not mapped to a learning day.');
        }
        $timezone = $enrollment->cohort?->timezone ?: 'UTC';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $start = CarbonImmutable::parse($enrollment->starts_on->toDateString(), $timezone)->startOfDay();
        if ($today->lt($start)) {
            throw new AccessDeniedHttpException('Learning has not started for this enrollment.');
        }
        if ($dayIndex === 1) {
            return;
        }

        $previousLessons = KcaLesson::query()
            ->where('kca_module_id', $module->getKey())
            ->where('day_index', $dayIndex - 1)
            ->pluck('id');
        if ($previousLessons->isEmpty()) {
            throw new AccessDeniedHttpException('Previous daily bundle is not configured.');
        }
        $completed = KcaLessonProgress::query()
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->whereIn('kca_lesson_id', $previousLessons)
            ->whereNotNull('completed_at')
            ->get();
        if ($completed->count() < $previousLessons->count()) {
            throw new AccessDeniedHttpException('Complete the previous daily bundle before opening this day.');
        }
        $lastCompleted = $completed->max('completed_at');
        $completedOn = CarbonImmutable::parse($lastCompleted)->timezone($timezone)->startOfDay();
        if ($completedOn->gte($today)) {
            throw new AccessDeniedHttpException('The next daily bundle unlocks on the next eligible learning day.');
        }
    }
}
