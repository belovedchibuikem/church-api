<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Media\MediaAttachableType;
use App\Media\PublicMediaUrl;
use App\Models\MediaAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MediaAttachment */
class MediaAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attachable = $this->relationLoaded('attachable') ? $this->attachable : null;
        $file = $this->relationLoaded('fileAsset') ? $this->fileAsset : null;
        $metadata = is_array($file?->metadata) ? $file->metadata : [];

        return [
            'id' => $this->public_id,
            'role' => $this->role->value,
            'sort_order' => $this->sort_order,
            'image_url' => PublicMediaUrl::forAsset($file),
            'attachable' => [
                'type' => $attachable === null ? $this->attachable_type : MediaAttachableType::aliasFor($attachable),
                'id' => $attachable?->getRouteKey(),
                'label' => $this->attachableLabel($attachable),
            ],
            'file' => $file === null ? null : [
                'id' => $file->public_id,
                'purpose' => $file->purpose,
                'detected_mime_type' => $file->detected_mime_type,
                'byte_size' => $file->byte_size,
                'original_filename' => $metadata['original_filename'] ?? null,
                'status' => $file->status->value,
            ],
            'updated_at' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }

    private function attachableLabel(mixed $attachable): ?string
    {
        if ($attachable === null) {
            return null;
        }

        foreach (['name', 'title', 'slug'] as $attribute) {
            $value = $attachable->{$attribute} ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $attachable->getRouteKey();
    }
}
