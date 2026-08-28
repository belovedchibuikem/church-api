<?php

namespace App\Models;

use App\Media\HasMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
class ContentPage extends Model
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

    public function items(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'page_id');
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
        ];
    }
}
