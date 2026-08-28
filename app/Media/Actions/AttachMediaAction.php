<?php

namespace App\Media\Actions;

use App\Files\FileAssetClassification;
use App\Files\FileAssetStatus;
use App\Media\MediaAttachableType;
use App\Media\MediaRole;
use App\Models\ContentItem;
use App\Models\FileAsset;
use App\Models\MediaAttachment;
use App\Models\PressPublication;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttachMediaAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(
        Model $attachable,
        FileAsset $fileAsset,
        MediaRole $role,
        ?User $actor = null,
    ): MediaAttachment {
        $this->assertAttachable($attachable);
        $this->assertPublicImage($fileAsset);

        return DB::transaction(function () use ($attachable, $fileAsset, $role, $actor): MediaAttachment {
            $existing = MediaAttachment::query()
                ->where('attachable_type', $attachable->getMorphClass())
                ->where('attachable_id', $attachable->getKey())
                ->where('role', $role->value)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                $existing = new MediaAttachment;
                $existing->forceFill([
                    'attachable_type' => $attachable->getMorphClass(),
                    'attachable_id' => $attachable->getKey(),
                    'role' => $role,
                    'sort_order' => 0,
                ]);
            }

            $existing->forceFill(['file_asset_id' => $fileAsset->getKey()])->save();
            $this->syncDomainCover($attachable, $fileAsset, $role);
            $this->syncContentItemMeta($attachable, $fileAsset);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'media.attachment.saved',
                actor: $actor,
                targetType: 'media_attachment',
                targetId: $existing->public_id,
                metadata: [
                    'attachable_type' => MediaAttachableType::aliasFor($attachable),
                    'attachable_id' => $attachable->getRouteKey(),
                    'file_asset_id' => $fileAsset->public_id,
                    'role' => $role->value,
                ],
            ));

            return $existing->load(['fileAsset']);
        }, attempts: 3);
    }

    private function assertAttachable(Model $attachable): void
    {
        MediaAttachableType::aliasFor($attachable);
        if (! $attachable->exists) {
            throw new InvalidArgumentException('The attachable record must be persisted.');
        }
    }

    private function assertPublicImage(FileAsset $fileAsset): void
    {
        if ($fileAsset->status !== FileAssetStatus::Available || $fileAsset->deleted_at !== null) {
            throw new InvalidArgumentException('Only available files can be attached as public media.');
        }

        if ($fileAsset->classification !== FileAssetClassification::Public) {
            throw new InvalidArgumentException('Public media must use a public file classification.');
        }

        $mime = (string) $fileAsset->detected_mime_type;
        if (! str_starts_with($mime, 'image/')) {
            throw new InvalidArgumentException('Public media attachments must be images.');
        }
    }

    private function syncDomainCover(Model $attachable, FileAsset $fileAsset, MediaRole $role): void
    {
        if ($attachable instanceof PressPublication && in_array($role, [MediaRole::Cover, MediaRole::Hero], true)) {
            $attachable->forceFill(['cover_file_asset_id' => $fileAsset->getKey()])->save();
        }
    }

    private function syncContentItemMeta(Model $attachable, FileAsset $fileAsset): void
    {
        if (! $attachable instanceof ContentItem) {
            return;
        }

        $meta = is_array($attachable->meta) ? $attachable->meta : [];
        $meta['file_asset_id'] = $fileAsset->public_id;
        $meta['image_url'] = rtrim((string) config('app.url'), '/').'/api/v1/media/'.$fileAsset->public_id;
        $attachable->forceFill(['meta' => $meta])->save();
    }
}
