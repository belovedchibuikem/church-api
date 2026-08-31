<?php

namespace App\Support\Kca;

use App\Models\KcaLesson;
use App\Models\KcaModule;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class PublishKcaModuleAction
{
    public function __construct(
        private MapKcaModuleDaysAction $mapDays,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(KcaModule $module, User $actor): KcaModule
    {
        return DB::transaction(function () use ($module, $actor): KcaModule {
            $locked = KcaModule::query()->lockForUpdate()->findOrFail($module->getKey());
            if ($locked->published_at !== null) {
                return $locked;
            }
            $unmapped = KcaLesson::query()->whereBelongsTo($locked, 'module')->whereNull('day_index')->exists();
            $lessons = KcaLesson::query()->whereBelongsTo($locked, 'module')->orderBy('sequence')->get();
            $this->mapDays->handle(
                $locked,
                $unmapped ? null : $lessons->pluck('day_index')->map(fn ($day): int => (int) $day)->values()->all(),
                $actor,
            );

            $locked->forceFill(['published_at' => now()->utc(), 'is_active' => true])->save();
            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.module.published',
                actor: $actor,
                targetType: 'kca_module',
                targetId: $locked->public_id,
                metadata: ['duration_days' => $locked->duration_days],
            ));

            return $locked->fresh() ?? $locked;
        }, attempts: 3);
    }
}
