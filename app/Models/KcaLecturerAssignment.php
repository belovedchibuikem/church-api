<?php

namespace App\Models;

use Database\Factories\KcaLecturerAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kca_module_id', 'kca_cohort_id', 'lecturer_person_id', 'assigned_by_user_id', 'starts_at', 'ends_at'])]
class KcaLecturerAssignment extends Model
{
    /** @use HasFactory<KcaLecturerAssignmentFactory> */
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

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(KcaCohort::class, 'kca_cohort_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'lecturer_person_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }
}
