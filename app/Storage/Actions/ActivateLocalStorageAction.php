<?php

namespace App\Storage\Actions;

use App\Models\ObjectStorageConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class ActivateLocalStorageAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(?User $actor = null): void
    {
        DB::transaction(function () use ($actor): void {
            $configurations = ObjectStorageConfiguration::query()
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            foreach ($configurations as $configuration) {
                $configuration->forceFill([
                    'is_active' => false,
                    'activated_at' => null,
                ])->save();
            }

            if ($configurations->isNotEmpty()) {
                $this->recordAuditEvent->handle(new AuditEventData(
                    action: 'platform.object_storage.deactivated',
                    actor: $actor,
                    targetType: 'object_storage_configuration',
                    targetId: 's3',
                    scopeType: 'global',
                    scopeId: 'platform',
                    metadata: ['active_provider' => 'local'],
                ));
            }
        }, attempts: 3);
    }
}
