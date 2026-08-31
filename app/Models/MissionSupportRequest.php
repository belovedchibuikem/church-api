<?php

namespace App\Models;

use Database\Factories\MissionSupportRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'requested_by_person_id', 'crusade_id', 'title', 'category', 'priority', 'status',
    'amount_minor', 'currency', 'details', 'idempotency_key_hash',
])]
class MissionSupportRequest extends Model
{
    /** @use HasFactory<MissionSupportRequestFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'requested_by_person_id');
    }

    public function crusade(): BelongsTo
    {
        return $this->belongsTo(Crusade::class);
    }
}
