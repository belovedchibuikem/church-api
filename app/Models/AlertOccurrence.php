<?php

namespace App\Models;

use App\Reporting\AlertOccurrenceStatus;
use Database\Factories\AlertOccurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden(['condition_fingerprint_hash', 'condition_reference_key', 'summary'])]
class AlertOccurrence extends Model
{
    /** @use HasFactory<AlertOccurrenceFactory> */
    use HasFactory, HasUlids;

    protected $attributes = [
        'status' => AlertOccurrenceStatus::Open->value,
        'active_marker' => 1,
    ];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => AlertOccurrenceStatus::class,
            'active_marker' => 'integer',
            'summary' => 'encrypted',
            'opened_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
