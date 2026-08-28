<?php

namespace App\Models;

use Database\Factories\KcaEvidenceSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([])]
#[Hidden(['idempotency_key_hash'])]
class KcaEvidenceSubmission extends Model
{
    /** @use HasFactory<KcaEvidenceSubmissionFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(KcaAssignment::class, 'kca_assignment_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(KcaEnrollment::class, 'kca_enrollment_id');
    }

    public function fileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'submitted_by_person_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(KcaEvidenceReview::class, 'kca_evidence_submission_id');
    }

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime'];
    }
}
