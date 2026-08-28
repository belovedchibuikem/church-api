<?php

namespace App\Models;

use App\Events\EventRegistrationStatus;
use Database\Factories\EventRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([])]
#[Hidden(['idempotency_scope_hash'])]
class EventRegistration extends Model
{
    /** @use HasFactory<EventRegistrationFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(MinistryEvent::class, 'ministry_event_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(EventAttendance::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(EventFeedback::class);
    }

    public function paymentIntent(): HasOne
    {
        return $this->hasOne(PaymentIntent::class);
    }

    protected function casts(): array
    {
        return ['status' => EventRegistrationStatus::class, 'registered_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime'];
    }
}
