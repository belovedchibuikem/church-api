<?php

namespace App\Models;

use App\Kca\KcaAssignmentState;
use Database\Factories\KcaEvidenceReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class KcaEvidenceReview extends Model
{
    /** @use HasFactory<KcaEvidenceReviewFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function evidenceSubmission(): BelongsTo
    {
        return $this->belongsTo(KcaEvidenceSubmission::class, 'kca_evidence_submission_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'reviewer_person_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    protected function casts(): array
    {
        return ['outcome' => KcaAssignmentState::class, 'reviewed_at' => 'immutable_datetime'];
    }
}
