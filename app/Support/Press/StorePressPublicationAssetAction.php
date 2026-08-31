<?php

namespace App\Support\Press;

use App\Files\FileAssetStatus;
use App\Models\FileAsset;
use App\Models\PressPublication;
use App\Models\PressPublicationAsset;
use App\Models\User;
use App\Press\PressAssetFormat;
use App\Press\PressAssetProcessingStatus;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use DomainException;
use Illuminate\Support\Facades\DB;

class StorePressPublicationAssetAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        PressPublication $publication,
        FileAsset $fileAsset,
        PressAssetFormat $format,
        User $actor,
        bool $required = false,
        ?string $label = null,
        ?string $languageCode = null,
    ): PressPublicationAsset {
        return DB::transaction(function () use ($publication, $fileAsset, $format, $actor, $required, $label, $languageCode): PressPublicationAsset {
            $lockedPublication = PressPublication::query()->lockForUpdate()->findOrFail($publication->getKey());
            $lockedAsset = FileAsset::query()->lockForUpdate()->findOrFail($fileAsset->getKey());

            if ($lockedAsset->status !== FileAssetStatus::Available || $lockedAsset->deleted_at !== null) {
                throw new DomainException('Press assets must be available and not deleted.');
            }

            $current = PressPublicationAsset::query()
                ->where('press_publication_id', $lockedPublication->getKey())
                ->where('asset_format', $format->value)
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();

            $version = 1;
            if ($current !== null) {
                $current->is_current = false;
                $current->save();
                $version = $current->version + 1;
            }

            $record = new PressPublicationAsset;
            $record->forceFill([
                'press_publication_id' => $lockedPublication->getKey(),
                'file_asset_id' => $lockedAsset->getKey(),
                'asset_format' => $format,
                'language_code' => $languageCode,
                'version' => $version,
                'is_current' => true,
                'is_required' => $required,
                'processing_status' => PressAssetProcessingStatus::Ready,
                'label' => $label,
                'checksum' => is_array($lockedAsset->metadata) ? ($lockedAsset->metadata['checksum'] ?? null) : null,
            ]);
            $record->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'press.publication.asset_stored',
                actor: $actor,
                targetType: 'press_publication',
                targetId: $lockedPublication->public_id,
                scopeType: 'press_publication',
                scopeId: $lockedPublication->public_id,
                metadata: [
                    'asset_format' => $format->value,
                    'version' => $version,
                    'file_asset_id' => $lockedAsset->public_id,
                ],
            ));

            return $record;
        }, attempts: 3);
    }
}
