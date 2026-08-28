<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrayerRequestAssignment extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function prayerRequest(): BelongsTo
    {
        return $this->belongsTo(PrayerRequest::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_to_person_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    protected function casts(): array
    {
        return ['assigned_at' => 'immutable_datetime'];
    }
}
