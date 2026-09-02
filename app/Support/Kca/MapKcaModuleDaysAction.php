<?php

namespace App\Support\Kca;

use App\Models\KcaLesson;
use App\Models\KcaModule;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MapKcaModuleDaysAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<int, int>|null  $dayIndexes  sequence-ordered day indexes; null uses even distribution
     */
    public function handle(KcaModule $module, ?array $dayIndexes, User $actor): KcaModule
    {
        return DB::transaction(function () use ($module, $dayIndexes, $actor): KcaModule {
            $locked = KcaModule::query()->lockForUpdate()->findOrFail($module->getKey());
            $lessons = KcaLesson::query()->whereBelongsTo($locked, 'module')->orderBy('sequence')->lockForUpdate()->get();
            if ($lessons->isEmpty()) {
                throw new InvalidArgumentException('Map days after creating at least one lesson.');
            }
            $mapping = $dayIndexes ?? KcaDailyBundleMapper::evenDistribution($lessons->count(), (int) $locked->duration_days);
            if (count($mapping) !== $lessons->count()) {
                throw new InvalidArgumentException('Day mapping must include every lesson exactly once.');
            }
            $usedDays = [];
            foreach ($mapping as $day) {
                $day = (int) $day;
                if ($day < 1 || $day > (int) $locked->duration_days) {
                    throw new InvalidArgumentException('Each mapped day must be between 1 and duration_days.');
                }
                $usedDays[$day] = true;
            }
            if ($lessons->count() >= (int) $locked->duration_days) {
                for ($day = 1; $day <= (int) $locked->duration_days; $day++) {
                    if (! isset($usedDays[$day])) {
                        throw new InvalidArgumentException("Day {$day} has no required lesson. Add a lesson or activity before publishing.");
                    }
                }
            }
            foreach ($lessons->values() as $index => $lesson) {
                $lesson->forceFill(['day_index' => (int) $mapping[$index]])->save();
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.module.days_mapped',
                actor: $actor,
                targetType: 'kca_module',
                targetId: $locked->public_id,
                metadata: ['duration_days' => $locked->duration_days, 'lessons' => $lessons->count()],
            ));

            return $locked->fresh(['lessons']) ?? $locked;
        }, attempts: 3);
    }
}
