<?php

namespace App\Models;

use Database\Factories\KcaLessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kca_module_id', 'code', 'title', 'sequence'])]
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

    protected function casts(): array
    {
        return ['sequence' => 'integer'];
    }
}
