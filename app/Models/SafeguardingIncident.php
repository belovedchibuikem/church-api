<?php

namespace App\Models;

use App\Safeguarding\IncidentSeverity;
use App\Safeguarding\IncidentStatus;
use Database\Factories\SafeguardingIncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['concern_type', 'severity', 'restricted_summary', 'occurred_at'])]
#[Hidden(['restricted_summary'])]
class SafeguardingIncident extends Model
{
    /** @use HasFactory<SafeguardingIncidentFactory> */
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'subject_person_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'restricted_summary' => 'encrypted',
            'occurred_at' => 'immutable_datetime',
            'reported_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
