<?php

namespace App\Models;

use Database\Factories\MentorAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
#[Hidden(['idempotency_scope_hash', 'payload_fingerprint'])]
class MentorAssignment extends Model
{
    /** @use HasFactory<MentorAssignmentFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function soulJourney(): BelongsTo
    {
        return $this->belongsTo(MissionSoulJourney::class, 'mission_soul_journey_id');
    }

    public function teamAssignment(): BelongsTo
    {
        return $this->belongsTo(MissionTeamAssignment::class, 'mission_team_assignment_id');
    }

    public function followUpInteractions(): HasMany
    {
        return $this->hasMany(FollowUpInteraction::class);
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
