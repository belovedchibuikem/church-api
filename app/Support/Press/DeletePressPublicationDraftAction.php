<?php

namespace App\Support\Press;

use App\Models\PressPublication;
use DomainException;
use Illuminate\Support\Facades\DB;

class DeletePressPublicationDraftAction
{
    public function handle(PressPublication $publication): void
    {
        DB::transaction(function () use ($publication): void {
            $locked = PressPublication::query()->lockForUpdate()->findOrFail($publication->getKey());

            if (! $locked->status->allowsHardDelete()) {
                throw new DomainException('Only unsubmitted drafts and manuscripts may be deleted.');
            }

            if ($locked->isbn !== null || $locked->published_at !== null) {
                throw new DomainException('Publications with identifiers or publish history cannot be deleted.');
            }

            if ($locked->reviews()->exists() || $locked->translations()->exists()) {
                throw new DomainException('Publications with reviews or translations cannot be deleted.');
            }

            $locked->delete();
        }, attempts: 3);
    }
}
