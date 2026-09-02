<?php

namespace App\Support\Kca;

use App\Models\KcaLesson;
use App\Models\KcaModule;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateKcaLessonAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{summary?: string|null, body?: string|null, content_url?: string|null, lesson_type?: string|null, day_index?: int|null}  $content
     */
    public function handle(
        KcaLesson $lesson,
        string $code,
        string $title,
        int $sequence,
        User $actor,
        array $content = [],
    ): KcaLesson {
        $normalizedCode = Str::squish($code);
        $normalizedTitle = Str::squish($title);

        if ($normalizedCode === '' || Str::length($normalizedCode) > 50) {
            throw new InvalidArgumentException('KCA lesson codes must contain between 1 and 50 characters.');
        }

        if ($normalizedTitle === '' || Str::length($normalizedTitle) > 191) {
            throw new InvalidArgumentException('KCA lesson titles must contain between 1 and 191 characters.');
        }

        if ($sequence < 1 || $sequence > 65535) {
            throw new InvalidArgumentException('KCA lesson sequences must be between 1 and 65535.');
        }

        return DB::transaction(function () use ($lesson, $normalizedCode, $normalizedTitle, $sequence, $actor, $content): KcaLesson {
            $locked = KcaLesson::query()->lockForUpdate()->findOrFail($lesson->getKey());
            $lockedModule = KcaModule::query()->lockForUpdate()->findOrFail($locked->kca_module_id);

            if ($lockedModule->published_at !== null) {
                throw new InvalidArgumentException('Lessons in published modules cannot be edited.');
            }

            $duplicate = KcaLesson::query()
                ->whereBelongsTo($lockedModule, 'module')
                ->whereKeyNot($locked->getKey())
                ->where(function ($query) use ($normalizedCode, $sequence): void {
                    $query->where('code', $normalizedCode)->orWhere('sequence', $sequence);
                })
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw new InvalidArgumentException('A KCA lesson with this code or sequence already exists for the module.');
            }

            $locked->forceFill([
                'code' => $normalizedCode,
                'title' => $normalizedTitle,
                'sequence' => $sequence,
                'summary' => array_key_exists('summary', $content) ? ($content['summary'] !== null && $content['summary'] !== '' ? Str::squish((string) $content['summary']) : null) : $locked->summary,
                'body' => array_key_exists('body', $content) ? ($content['body'] !== null && $content['body'] !== '' ? (string) $content['body'] : null) : $locked->body,
                'content_url' => array_key_exists('content_url', $content) ? ($content['content_url'] !== null && $content['content_url'] !== '' ? Str::squish((string) $content['content_url']) : null) : $locked->content_url,
                'lesson_type' => array_key_exists('lesson_type', $content) ? Str::squish((string) ($content['lesson_type'] ?? 'text')) : $locked->lesson_type,
                'day_index' => array_key_exists('day_index', $content) ? ($content['day_index'] !== null ? (int) $content['day_index'] : null) : $locked->day_index,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.lesson.updated',
                actor: $actor,
                targetType: 'kca_lesson',
                targetId: $locked->public_id,
                metadata: [
                    'module_id' => $lockedModule->public_id,
                    'code' => $normalizedCode,
                    'sequence' => $sequence,
                ],
            ));

            return $locked->refresh();
        }, attempts: 3);
    }
}
