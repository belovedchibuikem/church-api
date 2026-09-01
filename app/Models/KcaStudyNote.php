<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kca_enrollment_id', 'kca_lesson_id', 'kca_chapter_id', 'title', 'body'])]
class KcaStudyNote extends Model
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

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(KcaEnrollment::class, 'kca_enrollment_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(KcaLesson::class, 'kca_lesson_id');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(KcaChapter::class, 'kca_chapter_id');
    }
}
