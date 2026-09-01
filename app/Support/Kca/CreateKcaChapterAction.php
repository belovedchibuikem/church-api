<?php

namespace App\Support\Kca;

use App\Models\KcaChapter;
use App\Models\KcaLesson;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateKcaChapterAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{summary?: string|null, body?: string|null, content_url?: string|null, estimated_minutes?: int|null}  $content
     */
    public function handle(KcaLesson $lesson, string $code, string $title, int $sequence, User $actor, array $content = []): KcaChapter
    {
        $normalizedCode = Str::squish($code);
        $normalizedTitle = Str::squish($title);

        if ($normalizedCode === '' || Str::length($normalizedCode) > 50) {
            throw new InvalidArgumentException('KCA chapter codes must contain between 1 and 50 characters.');
        }
        if ($normalizedTitle === '' || Str::length($normalizedTitle) > 191) {
            throw new InvalidArgumentException('KCA chapter titles must contain between 1 and 191 characters.');
        }
        if ($sequence < 1 || $sequence > 65535) {
            throw new InvalidArgumentException('KCA chapter sequences must be between 1 and 65535.');
        }

        return DB::transaction(function () use ($lesson, $normalizedCode, $normalizedTitle, $sequence, $actor, $content): KcaChapter {
            $lockedLesson = KcaLesson::query()->lockForUpdate()->findOrFail($lesson->getKey());
            $duplicate = KcaChapter::query()
                ->where('kca_lesson_id', $lockedLesson->getKey())
                ->where(function ($query) use ($normalizedCode, $sequence): void {
                    $query->where('code', $normalizedCode)->orWhere('sequence', $sequence);
                })
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw new InvalidArgumentException('A KCA chapter with this code or sequence already exists for the lesson.');
            }

            $chapter = KcaChapter::query()->create([
                'kca_lesson_id' => $lockedLesson->getKey(),
                'code' => $normalizedCode,
                'title' => $normalizedTitle,
                'sequence' => $sequence,
                'summary' => isset($content['summary']) ? Str::squish((string) $content['summary']) : null,
                'body' => isset($content['body']) ? (string) $content['body'] : null,
                'content_url' => isset($content['content_url']) ? Str::squish((string) $content['content_url']) : null,
                'estimated_minutes' => isset($content['estimated_minutes']) ? (int) $content['estimated_minutes'] : null,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.chapter.created',
                actor: $actor,
                targetType: 'kca_chapter',
                targetId: $chapter->public_id,
                metadata: [
                    'lesson_id' => $lockedLesson->public_id,
                    'code' => $normalizedCode,
                    'sequence' => $sequence,
                ],
            ));

            return $chapter;
        }, attempts: 3);
    }
}
