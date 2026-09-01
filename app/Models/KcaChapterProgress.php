<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class KcaChapterProgress extends Model
{
    use HasUlids;

    protected $table = 'kca_chapter_progress';

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(KcaEnrollment::class, 'kca_enrollment_id');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(KcaChapter::class, 'kca_chapter_id');
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
