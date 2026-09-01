<?php

namespace App\Models;

use Database\Factories\KcaLessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kca_module_id',
    'code',
    'title',
    'summary',
    'body',
    'content_url',
    'estimated_minutes',
    'sequence',
    'day_index',
    'lesson_type',
    'requires_acknowledgement',
])]
class KcaLesson extends Model
{
    /** @use HasFactory<KcaLessonFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(KcaModule::class, 'kca_module_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(KcaAttendance::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(KcaChapter::class)->orderBy('sequence');
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'day_index' => 'integer',
            'estimated_minutes' => 'integer',
            'requires_acknowledgement' => 'boolean',
        ];
    }
}
