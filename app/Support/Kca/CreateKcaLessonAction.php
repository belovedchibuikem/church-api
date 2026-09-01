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

class CreateKcaLessonAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{summary?: string|null, body?: string|null, content_url?: string|null, estimated_minutes?: int|null, lesson_type?: string|null, day_index?: int|null, requires_acknowledgement?: bool|null, chapters?: list<array<string, mixed>>}  $content
     */
    public function handle(KcaModule $module, string $code, string $title, int $sequence, User $actor, array $content = []): KcaLesson
    {
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

        return DB::transaction(function () use ($module, $normalizedCode, $normalizedTitle, $sequence, $actor, $content): KcaLesson {
            $lockedModule = KcaModule::query()->lockForUpdate()->findOrFail($module->getKey());
            $duplicate = KcaLesson::query()
                ->whereBelongsTo($lockedModule, 'module')
                ->where(function ($query) use ($normalizedCode, $sequence): void {
                    $query->where('code', $normalizedCode)->orWhere('sequence', $sequence);
                })
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw new InvalidArgumentException('A KCA lesson with this code or sequence already exists for the module.');
            }

            $lesson = KcaLesson::query()->create([
                'kca_module_id' => $lockedModule->getKey(),
                'code' => $normalizedCode,
                'title' => $normalizedTitle,
                'sequence' => $sequence,
                'summary' => isset($content['summary']) ? Str::squish((string) $content['summary']) : null,
                'body' => isset($content['body']) ? (string) $content['body'] : null,
                'content_url' => isset($content['content_url']) ? Str::squish((string) $content['content_url']) : null,
                'estimated_minutes' => isset($content['estimated_minutes']) ? (int) $content['estimated_minutes'] : null,
                'lesson_type' => isset($content['lesson_type']) ? Str::squish((string) $content['lesson_type']) : 'text',
                'day_index' => isset($content['day_index']) ? (int) $content['day_index'] : null,
                'requires_acknowledgement' => (bool) ($content['requires_acknowledgement'] ?? true),
            ]);

            $chapters = $content['chapters'] ?? [];
            if (is_array($chapters) && $chapters !== []) {
                $sequenceCursor = 1;
                foreach ($chapters as $chapter) {
                    if (! is_array($chapter)) {
                        continue;
                    }
                    app(CreateKcaChapterAction::class)->handle(
                        $lesson,
                        (string) ($chapter['code'] ?? 'CH'.str_pad((string) $sequenceCursor, 2, '0', STR_PAD_LEFT)),
                        (string) ($chapter['title'] ?? 'Chapter '.$sequenceCursor),
                        (int) ($chapter['sequence'] ?? $sequenceCursor),
                        $actor,
                        [
                            'summary' => $chapter['summary'] ?? null,
                            'body' => $chapter['body'] ?? null,
                            'content_url' => $chapter['content_url'] ?? null,
                            'estimated_minutes' => $chapter['estimated_minutes'] ?? null,
                        ],
                    );
                    $sequenceCursor++;
                }
            } elseif (($lesson->body || $lesson->summary || $lesson->content_url) && $lesson->chapters()->doesntExist()) {
                app(CreateKcaChapterAction::class)->handle(
                    $lesson,
                    'CH01',
                    'Chapter 1',
                    1,
                    $actor,
                    [
                        'summary' => $lesson->summary,
                        'body' => $lesson->body,
                        'content_url' => $lesson->content_url,
                        'estimated_minutes' => $lesson->estimated_minutes,
                    ],
                );
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.lesson.created',
                actor: $actor,
                targetType: 'kca_lesson',
                targetId: $lesson->public_id,
                metadata: [
                    'module_id' => $lockedModule->public_id,
                    'code' => $normalizedCode,
                    'sequence' => $sequence,
                ],
            ));

            return $lesson;
        }, attempts: 3);
    }
}
