<?php

namespace App\Support\Kca;

use App\Files\Actions\ApproveFileAssetAction;
use App\Files\FileAssetStatus;
use App\Models\FileAsset;
use App\Models\KcaGovernanceConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfigureKcaGovernanceAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly ApproveFileAssetAction $approveFile,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?User $actor = null): KcaGovernanceConfiguration
    {
        return DB::transaction(function () use ($input, $actor): KcaGovernanceConfiguration {
            $configuration = KcaGovernanceConfiguration::query()->lockForUpdate()->first()
                ?? new KcaGovernanceConfiguration;

            $configuration->fill($this->normalizeInput($input));
            $configuration->forceFill([
                'is_active' => true,
                'configuration_revision' => $configuration->exists ? $configuration->configuration_revision + 1 : 1,
            ])->save();

            $this->approveAdmissionAsset($configuration->admission_letterhead_file_asset_id, $actor);
            $this->approveAdmissionAsset($configuration->admission_signature_file_asset_id, $actor);

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

            return $configuration->refresh()->load([
                'admissionLetterheadFile:id,public_id',
                'admissionSignatureFile:id,public_id',
            ]);
        });
    }

    private function approveAdmissionAsset(?int $fileAssetId, ?User $actor): void
    {
        if ($fileAssetId === null || $actor === null) {
            return;
        }

        $asset = FileAsset::query()->find($fileAssetId);

        if ($asset === null || $asset->status === FileAssetStatus::Available) {
            return;
        }

        try {
            $this->approveFile->handle($asset, $actor);
        } catch (InvalidArgumentException) {
            // Already approved, rejected, or otherwise unavailable.
        }
    }

    /** @param  array<string, mixed>  $input */
    private function normalizeInput(array $input): array
    {
        foreach (['admission_letterhead_file_asset_id', 'admission_signature_file_asset_id'] as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];
            if ($value === null || $value === '') {
                $input[$key] = null;

                continue;
            }

            $input[$key] = FileAsset::query()->where('public_id', (string) $value)->value('id');
        }

        return $input;
    }
}
