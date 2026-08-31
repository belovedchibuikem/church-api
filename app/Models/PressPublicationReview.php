<?php

namespace App\Models;

use App\Press\PressReviewDecision;
use App\Press\PressReviewStage;
use Database\Factories\PressPublicationReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class PressPublicationReview extends Model
{
    /** @use HasFactory<PressPublicationReviewFactory> */
    use HasFactory, HasUlids;

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(PressPublication::class, 'press_publication_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'reviewer_person_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stage' => PressReviewStage::class,
            'decision' => PressReviewDecision::class,
            'checklist' => 'array',
            'comments_public' => 'boolean',
            'decided_at' => 'immutable_datetime',
        ];
    }
}
