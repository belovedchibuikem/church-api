<?php

namespace App\Branding\Actions;

use App\Branding\PlatformBrandAssetKind;
use App\Branding\PlatformBrandingPresenter;
use App\Exceptions\FileAssetValidationException;
use App\Files\Actions\ApproveFileAssetAction;
use App\Files\Actions\StoreFileAssetAction;
use App\Files\Data\StoreFileAssetData;
use App\Files\FileAssetClassification;
use App\Models\PlatformBrandingConfiguration;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UploadPlatformBrandAssetAction
{
    public function __construct(
        private readonly StoreFileAssetAction $storeFile,
        private readonly ApproveFileAssetAction $approveFile,
        private readonly RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        PlatformBrandAssetKind $kind,
        UploadedFile $file,
        string $idempotencyKey,
        User $actor,
    ): PlatformBrandingConfiguration {
        $asset = $this->storeFile->handle(new StoreFileAssetData(
            file: $file,
            purpose: $kind->purpose(),
            classification: FileAssetClassification::Public,
            idempotencyKey: $idempotencyKey,
            owner: null,
            actor: $actor,
        ));
        $asset = $this->approveFile->handle($asset, $actor);

        if (! in_array($asset->detected_mime_type, $kind->allowedMimeTypes(), true)) {
            throw new FileAssetValidationException('mime_type_not_allowed');
        }

        return DB::transaction(function () use ($kind, $asset, $actor): PlatformBrandingConfiguration {
            $configuration = PlatformBrandingConfiguration::query()->lockForUpdate()->first()
                ?? new PlatformBrandingConfiguration;

            if (! $configuration->exists) {
                $configuration->app_name = PlatformBrandingPresenter::DEFAULT_APP_NAME;
            }

            $configuration->forceFill([
                $kind->column() => $asset->getKey(),
                'configuration_revision' => $configuration->exists
                    ? $configuration->configuration_revision + 1
                    : 1,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'platform.branding.asset_uploaded',
                actor: $actor,
                targetType: 'platform_branding_configuration',
                targetId: (string) $configuration->getKey(),
                scopeType: 'global',
                scopeId: 'platform',
                metadata: [
                    'kind' => $kind->value,
                    'file_asset_id' => $asset->public_id,
                    'configuration_revision' => $configuration->configuration_revision,
                ],
            ));

            return $configuration->refresh()->load(['logoFile', 'faviconFile']);
        });
    }
}
