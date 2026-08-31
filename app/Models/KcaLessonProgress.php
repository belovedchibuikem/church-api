<?php

namespace App\Models;

use Database\Factories\KcaLessonProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class KcaLessonProgress extends Model
{
    /** @use HasFactory<KcaLessonProgressFactory> */
    use HasFactory, HasUlids;

    protected $table = 'kca_lesson_progress';

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(KcaEnrollment::class, 'kca_enrollment_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(KcaLesson::class, 'kca_lesson_id');
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
