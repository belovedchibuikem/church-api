<?php

namespace App\Support\Kca;

use App\Models\KcaGovernanceConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class ConfigureKcaGovernanceAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?User $actor = null): KcaGovernanceConfiguration
    {
        return DB::transaction(function () use ($input, $actor): KcaGovernanceConfiguration {
            $configuration = KcaGovernanceConfiguration::query()->lockForUpdate()->first()
                ?? new KcaGovernanceConfiguration;

            $configuration->fill($input);
            $configuration->forceFill([
                'is_active' => true,
                'configuration_revision' => $configuration->exists ? $configuration->configuration_revision + 1 : 1,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.governance.configured',
                actor: $actor,
                targetType: 'kca_governance_configuration',
                targetId: (string) $configuration->getKey(),
                scopeType: 'global',
                scopeId: 'platform',
                metadata: [
                    'pass_threshold_percent' => $configuration->pass_threshold_percent,
                    'attendance_threshold_percent' => $configuration->attendance_threshold_percent,
                    'require_final_assessment' => $configuration->require_final_assessment,
                    'require_signed_pdf' => $configuration->require_signed_pdf,
                    'configuration_revision' => $configuration->configuration_revision,
                ],
            ));

            return $configuration->refresh();
        });
    }
}
