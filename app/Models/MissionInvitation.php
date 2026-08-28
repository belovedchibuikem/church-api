<?php

namespace App\Models;

use App\Mission\MissionInvitationStatus;
use Database\Factories\MissionInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['crusade_id', 'requester_person_id', 'requested_location_id'])]
class MissionInvitation extends Model
{
    /** @use HasFactory<MissionInvitationFactory> */
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'requester_person_id');
    }

    public function requestedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'requested_location_id');
    }

    protected function casts(): array
    {
        return [
            'status' => MissionInvitationStatus::class,
            'status_changed_at' => 'immutable_datetime',
        ];
    }
}
