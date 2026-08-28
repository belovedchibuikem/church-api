<?php

namespace App\Media;

use App\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMedia
{
    public function mediaAttachments(): MorphMany
    {
        return $this->morphMany(MediaAttachment::class, 'attachable')->orderBy('sort_order')->orderBy('id');
    }
}
