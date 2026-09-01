<?php

namespace App\Models;

use Database\Factories\KcaEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([])]
class KcaEnrollment extends Model
{
    /** @use HasFactory<KcaEnrollmentFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(KcaApplication::class, 'kca_application_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(KcaYear::class, 'kca_year_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(KcaCohort::class, 'kca_cohort_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function mentorAssignments(): HasMany
    {
        return $this->hasMany(KcaMentorAssignment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(KcaAssignment::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(KcaCertificate::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(KcaAttendance::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(KcaLessonProgress::class);
    }

    public function studyNotes(): HasMany
    {
        return $this->hasMany(KcaStudyNote::class);
    }

    public function devotionalReadings(): HasMany
    {
        return $this->hasMany(KcaDevotionalReading::class);
    }

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date'];
    }
}
