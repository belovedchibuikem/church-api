<?php

namespace App\Queries\PublicEvents;

use App\Models\MinistryEvent;

class FindPublicEventQuery
{
    public function handle(string $publicId): MinistryEvent
    {
        return MinistryEvent::query()
            ->select([
                'id', 'public_id', 'location_id', 'category_code', 'name', 'starts_at', 'ends_at',
                'fee_amount_minor', 'fee_currency',
            ])
            ->with(['location:id,public_id,name,locality,timezone', 'mediaAttachments.fileAsset'])
            ->where('public_id', $publicId)
            ->publiclyUpcoming()
            ->firstOrFail();
    }
}
