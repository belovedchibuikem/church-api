<?php

namespace App\Models;

use Database\Factories\KcaAssessmentResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kca_enrollment_id', 'kca_module_id', 'kca_assignment_id', 'assessment_code', 'result_code', 'score', 'attempt_number', 'assessed_by_user_id', 'assessed_at'])]
class KcaAssessmentResult extends Model
{
    /** @use HasFactory<KcaAssessmentResultFactory> */
    use HasFactory, HasUlids;

    protected $attributes = ['attempt_number' => 1];

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

    public function module(): BelongsTo
    {
        return $this->belongsTo(KcaModule::class, 'kca_module_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(KcaAssignment::class, 'kca_assignment_id');
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'attempt_number' => 'integer',
            'assessed_at' => 'immutable_datetime',
        ];
    }
}
