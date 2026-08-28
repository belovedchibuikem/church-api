<?php

namespace App\Models;

use App\Church\HomeChurchApplicationStatus;
use App\Church\MeetingDay;
use Database\Factories\HomeChurchApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'applicant_person_id',
    'church_id',
    'location_id',
    'administrative_unit_id',
    'proposed_name',
    'expected_participants',
    'meeting_day',
    'meeting_time',
    'contact_email',
    'contact_phone',
    'guidelines_agreed_at',
])]
#[Hidden([
    'contact_email',
    'contact_phone',
    'public_idempotency_scope_hash',
    'public_payload_fingerprint',
])]
class HomeChurchApplication extends Model
{
    /** @use HasFactory<HomeChurchApplicationFactory> */
    use HasFactory, HasUlids;

    protected $attributes = ['status' => 'draft', 'active_marker' => 1];

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'applicant_person_id');
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function homeChurch(): BelongsTo
    {
        return $this->belongsTo(HomeChurch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(HomeChurchApplicationTransition::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expected_participants' => 'integer',
            'meeting_day' => MeetingDay::class,
            'contact_email' => 'encrypted',
            'contact_phone' => 'encrypted',
            'guidelines_agreed_at' => 'immutable_datetime',
            'status' => HomeChurchApplicationStatus::class,
            'active_marker' => 'integer',
            'status_changed_at' => 'immutable_datetime',
        ];
    }
}
