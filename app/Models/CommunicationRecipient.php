<?php

namespace App\Models;

use App\Communication\CommunicationRecipientStatus;
use Database\Factories\CommunicationRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([])]
class CommunicationRecipient extends Model
{
    /** @use HasFactory<CommunicationRecipientFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(CommunicationBroadcast::class, 'communication_broadcast_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(CommunicationDeliveryAttempt::class);
    }

    public function notification(): HasOne
    {
        return $this->hasOne(CommunicationNotification::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CommunicationRecipientStatus::class,
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
