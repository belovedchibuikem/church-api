<?php

namespace App\Media;

use App\Files\FileAssetClassification;
use App\Files\FileAssetStatus;
use App\Models\FileAsset;
use App\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Model;

final class PublicMediaUrl
{
    public static function forAsset(?FileAsset $asset): ?string
    {
        if (
            $asset === null
            || $asset->status !== FileAssetStatus::Available
            || $asset->classification !== FileAssetClassification::Public
            || $asset->deleted_at !== null
        ) {
            return null;
        }

        return rtrim((string) config('app.url'), '/').'/api/v1/media/'.$asset->public_id;
    }

    /**
     * @param  list<string>  $roles
     */
    public static function fromLoaded(Model $model, array $roles = ['cover', 'hero', 'thumbnail', 'avatar', 'logo']): ?string
    {
        if (! $model->relationLoaded('mediaAttachments')) {
            return null;
        }

        /** @var MediaAttachment|null $match */
        $match = $model->mediaAttachments->first(
            fn (MediaAttachment $attachment): bool => in_array($attachment->role->value, $roles, true),
        ) ?? $model->mediaAttachments->first();

        if ($match === null) {
            return null;
        }

        $asset = $match->relationLoaded('fileAsset') ? $match->fileAsset : null;

        return self::forAsset($asset);
    }
}
