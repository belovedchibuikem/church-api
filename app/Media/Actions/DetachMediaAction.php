<?php

namespace App\Media\Actions;

use App\Models\ContentItem;
use App\Models\MediaAttachment;
use App\Models\PressPublication;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;

class DetachMediaAction
{
    public function __construct(private readonly RecordAuditEventAction $recordAuditEvent) {}

    public function handle(MediaAttachment $attachment, ?User $actor = null): void
    {
        DB::transaction(function () use ($attachment, $actor): void {
            $locked = MediaAttachment::query()->lockForUpdate()->findOrFail($attachment->getKey());
            $attachable = $locked->attachable;
            $publicId = $locked->public_id;
            $role = $locked->role->value;
            $fileId = $locked->fileAsset?->public_id;

            if ($attachable instanceof PressPublication && in_array($role, ['cover', 'hero'], true)) {
                $attachable->forceFill(['cover_file_asset_id' => null])->save();
            }

            if ($attachable instanceof ContentItem) {
                $meta = is_array($attachable->meta) ? $attachable->meta : [];
                unset($meta['file_asset_id'], $meta['image_url']);
                $attachable->forceFill(['meta' => $meta === [] ? null : $meta])->save();
            }

            $locked->delete();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'media.attachment.removed',
                actor: $actor,
                targetType: 'media_attachment',
                targetId: $publicId,
                metadata: [
                    'role' => $role,
                    'file_asset_id' => $fileId,
                ],
            ));
        }, attempts: 3);
    }
}
