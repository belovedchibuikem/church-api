<?php

namespace App\Support\Kca;

use App\Exceptions\KcaIdempotencyConflictException;
use App\Models\KcaChapter;
use App\Models\KcaChapterProgress;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CompleteKcaChapterAction
{
    public function __construct(
        private CompleteKcaLessonAction $completeLesson,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        Person $person,
        KcaChapter $chapter,
        bool $acknowledged,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?string $unlockToken = null,
    ): KcaChapterProgress {
        $enrollment = KcaEnrollment::query()
            ->with('cohort')
            ->where('person_id', $person->getKey())
            ->latest('starts_on')
            ->first();
        if ($enrollment === null) {
            throw new AccessDeniedHttpException('Only activated KCA students can complete chapters.');
        }

        return DB::transaction(function () use ($enrollment, $chapter, $acknowledged, $idempotencyKey, $actor, $unlockToken, $person): KcaChapterProgress {
            $lockedChapter = KcaChapter::query()->with('lesson.module')->lockForUpdate()->findOrFail($chapter->getKey());
            $lesson = $lockedChapter->lesson;
            if ($lesson === null) {
                throw new InvalidArgumentException('This chapter is not attached to a lesson.');
            }
            $this->completeLesson->assertAccessible($enrollment, $lesson);
            $this->assertPreviousChaptersComplete($enrollment, $lesson, (int) $lockedChapter->sequence);

            $existing = KcaChapterProgress::query()
                ->where('kca_enrollment_id', $enrollment->getKey())
                ->where('kca_chapter_id', $lockedChapter->getKey())
                ->lockForUpdate()
                ->first();
            if ($existing?->completed_at !== null) {
                return $existing;
            }
            if ($idempotencyKey) {
                $byKey = KcaChapterProgress::query()
                    ->where('kca_enrollment_id', $enrollment->getKey())
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($byKey !== null) {
                    if ((int) $byKey->kca_chapter_id !== (int) $lockedChapter->getKey()) {
                        throw new KcaIdempotencyConflictException;
                    }

                    return $byKey;
                }
            }

            $now = now()->utc();
            $progress = $existing ?? new KcaChapterProgress;
            $progress->forceFill([
                'kca_enrollment_id' => $enrollment->getKey(),
                'kca_chapter_id' => $lockedChapter->getKey(),
                'started_at' => $progress->started_at ?? $now,
                'completed_at' => $now,
                'idempotency_key' => $idempotencyKey,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.chapter.completed',
                actor: $actor,
                targetType: 'kca_chapter',
                targetId: $lockedChapter->public_id,
                metadata: [
                    'enrollment_id' => $enrollment->public_id,
                    'lesson_id' => $lesson->public_id,
                ],
            ));

            if ($this->allChaptersComplete($enrollment, $lesson)) {
                $this->completeLesson->handle(
                    $person,
                    $lesson,
                    $acknowledged || ! $lesson->requires_acknowledgement,
                    $actor,
                    $idempotencyKey === null ? null : $idempotencyKey.'-lesson',
                    $unlockToken,
                );
            }

            return $progress;
        }, attempts: 3);
    }

    public function assertAccessible(KcaEnrollment $enrollment, KcaChapter $chapter): void
    {
        $lesson = $chapter->lesson ?? KcaLesson::query()->find($chapter->kca_lesson_id);
        if ($lesson === null) {
            throw new AccessDeniedHttpException('This chapter is not attached to a lesson.');
        }
        $this->completeLesson->assertAccessible($enrollment, $lesson);
        $this->assertPreviousChaptersComplete($enrollment, $lesson, (int) $chapter->sequence);
    }

    private function assertPreviousChaptersComplete(KcaEnrollment $enrollment, KcaLesson $lesson, int $sequence): void
    {
        if ($sequence <= 1) {
            return;
        }
        $previous = KcaChapter::query()
            ->where('kca_lesson_id', $lesson->getKey())
            ->where('sequence', '<', $sequence)
            ->pluck('id');
        $completed = KcaChapterProgress::query()
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->whereIn('kca_chapter_id', $previous)
            ->whereNotNull('completed_at')
            ->count();
        if ($completed < $previous->count()) {
            throw new AccessDeniedHttpException('Complete the previous chapter before opening this one.');
        }
    }

    private function allChaptersComplete(KcaEnrollment $enrollment, KcaLesson $lesson): bool
    {
        $ids = KcaChapter::query()->where('kca_lesson_id', $lesson->getKey())->pluck('id');
        if ($ids->isEmpty()) {
            return true;
        }
        $completed = KcaChapterProgress::query()
            ->where('kca_enrollment_id', $enrollment->getKey())
            ->whereIn('kca_chapter_id', $ids)
            ->whereNotNull('completed_at')
            ->count();

        return $completed >= $ids->count();
    }
}
