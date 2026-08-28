<?php

namespace App\Models;

use Database\Factories\CommunicationNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class CommunicationNotification extends Model
{
    /** @use HasFactory<CommunicationNotificationFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CommunicationRecipient::class, 'communication_recipient_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Eager-load path for in-app title/body from broadcast template.
     */
    public function scopeWithBroadcastContent($query)
    {
        return $query->with(['recipient.broadcast.template']);
    }

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime'];
    }
}
