<?php

namespace App\Models;

use Database\Factories\MissionTeamAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['crusade_id', 'person_id', 'role_code', 'assigned_at'])]
class MissionTeamAssignment extends Model
{
    /** @use HasFactory<MissionTeamAssignmentFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function crusade(): BelongsTo
    {
        return $this->belongsTo(Crusade::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function mentorAssignments(): HasMany
    {
        return $this->hasMany(MentorAssignment::class);
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
