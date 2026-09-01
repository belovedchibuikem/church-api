<?php

namespace App\Models;

use Database\Factories\KcaChapterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kca_lesson_id',
    'code',
    'title',
    'summary',
    'body',
    'content_url',
    'estimated_minutes',
    'sequence',
])]
class KcaChapter extends Model
{
    /** @use HasFactory<KcaChapterFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(KcaLesson::class, 'kca_lesson_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(KcaChapterProgress::class);
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'estimated_minutes' => 'integer',
        ];
    }
}
