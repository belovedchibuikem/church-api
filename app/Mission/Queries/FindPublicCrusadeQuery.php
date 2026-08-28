<?php

namespace App\Mission\Queries;

use App\Models\Crusade;

class FindPublicCrusadeQuery
{
    public function execute(string $publicId): Crusade
    {
        return Crusade::query()
            ->select(['id', 'public_id', 'name', 'location_id', 'starts_at', 'ends_at'])
            ->with([
                'location:id,public_id,country_id,name,locality,timezone,latitude,longitude',
                'location.country:id,iso_code,name',
                'mediaAttachments.fileAsset',
            ])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()->utc())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
