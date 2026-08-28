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

    public function handle(KcaModule $module, string $code, string $title, int $sequence, User $actor): KcaLesson
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

        return DB::transaction(function () use ($module, $normalizedCode, $normalizedTitle, $sequence, $actor): KcaLesson {
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
            ]);

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
