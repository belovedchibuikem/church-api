<?php

namespace App\Models;

use App\Church\FollowUpTaskStatus;
use App\Church\FollowUpTaskType;
use Database\Factories\FollowUpTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'first_timer_id',
    'assigned_to_person_id',
    'type',
    'due_at',
])]
class FollowUpTask extends Model
{
    /** @use HasFactory<FollowUpTaskFactory> */
    use HasFactory, HasUlids;

    protected $attributes = ['status' => 'pending'];

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function firstTimer(): BelongsTo
    {
        return $this->belongsTo(FirstTimer::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_to_person_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => FollowUpTaskType::class,
            'status' => FollowUpTaskStatus::class,
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
