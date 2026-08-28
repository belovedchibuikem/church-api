<?php

namespace App\Models;

use App\Communication\CommunicationChannel;
use App\Communication\CommunicationDeliveryStatus;
use Database\Factories\CommunicationDeliveryAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden(['idempotency_key_hash'])]
class CommunicationDeliveryAttempt extends Model
{
    /** @use HasFactory<CommunicationDeliveryAttemptFactory> */
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

    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannel::class,
            'status' => CommunicationDeliveryStatus::class,
            'attempted_at' => 'immutable_datetime',
        ];
    }
}
