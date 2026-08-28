<?php

namespace App\Models;

use App\Media\HasMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class ContentItem extends Model
{
    use HasMedia, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(ContentPage::class, 'page_id');
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'sort_order' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }
}
