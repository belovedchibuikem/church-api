<?php

namespace App\Files\Actions;

use App\Files\FileAssetStatus;
use App\Files\MalwareScanStatus;
use App\Models\FileAsset;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApproveFileAssetAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    public function handle(FileAsset $asset, User $actor): FileAsset
    {
        return DB::transaction(function () use ($asset, $actor): FileAsset {
            $locked = FileAsset::query()->lockForUpdate()->findOrFail($asset->getKey());

            if (in_array($locked->status, [FileAssetStatus::Rejected, FileAssetStatus::Deleted, FileAssetStatus::Available], true)) {
                throw new InvalidArgumentException(
                    "File assets in {$locked->status->value} status cannot be approved.",
                );
            }

            if (! in_array($locked->status, [FileAssetStatus::Quarantined, FileAssetStatus::Pending], true)) {
                throw new InvalidArgumentException(
                    "File assets in {$locked->status->value} status cannot be approved.",
                );
            }

            $now = now()->utc();
            $locked->forceFill([
                'malware_scan_status' => MalwareScanStatus::Clean,
                'status' => FileAssetStatus::Available,
                'available_at' => $now,
                'malware_scanned_at' => $locked->malware_scanned_at ?? $now,
            ])->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'files.asset.approved',
                actor: $actor,
                targetType: 'file_asset',
                targetId: $locked->public_id,
                metadata: [
                    'status' => $locked->status->value,
                    'malware_scan_status' => $locked->malware_scan_status->value,
                ],
            ));

            return $locked;
        }, attempts: 3);
    }
}
