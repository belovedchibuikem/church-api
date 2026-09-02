<?php

namespace App\Models;

use App\Kca\KcaApplicationState;
use Database\Factories\KcaApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['person_id', 'application_data', 'received_at', 'orientation_progress'])]
class KcaApplication extends Model
{
    /** @use HasFactory<KcaApplicationFactory> */
    use HasFactory, HasUlids;

    protected $attributes = ['status' => KcaApplicationState::Received->value];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function admissionDecision(): HasOne
    {
        return $this->hasOne(KcaAdmissionDecision::class);
    }

    public function admissionLetter(): HasOne
    {
        return $this->hasOne(KcaAdmissionLetter::class);
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(KcaEnrollment::class);
    }

    public function leadershipRecommendation(): HasOne
    {
        return $this->hasOne(KcaLeadershipRecommendation::class);
    }

    protected function casts(): array
    {
        return [
            'status' => KcaApplicationState::class,
            'application_data' => 'array',
            'received_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'orientation_progress' => 'array',
            'orientation_completed_at' => 'immutable_datetime',
        ];
    }
}
