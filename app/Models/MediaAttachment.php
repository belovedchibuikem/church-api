<?php

namespace App\Models;

use App\Media\MediaRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([])]
class MediaAttachment extends Model
{
    use HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function fileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => MediaRole::class,
            'sort_order' => 'integer',
        ];
    }
}
