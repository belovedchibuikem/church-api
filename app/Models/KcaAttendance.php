<?php

namespace App\Models;

use App\Kca\KcaAttendanceStatus;
use Database\Factories\KcaAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kca_enrollment_id', 'kca_lesson_id', 'status', 'session_on', 'recorded_by_user_id', 'recorded_at'])]
class KcaAttendance extends Model
{
    /** @use HasFactory<KcaAttendanceFactory> */
    use HasFactory, HasUlids;

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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => KcaAttendanceStatus::class,
            'session_on' => 'immutable_date',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
