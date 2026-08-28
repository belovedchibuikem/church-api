<?php

namespace App\Models;

use Database\Factories\KcaMentorAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kca_enrollment_id', 'mentor_person_id', 'assigned_by_user_id', 'starts_at', 'ends_at'])]
class KcaMentorAssignment extends Model
{
    /** @use HasFactory<KcaMentorAssignmentFactory> */
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

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'mentor_person_id');
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
