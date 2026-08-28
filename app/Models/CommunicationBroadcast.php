<?php

namespace App\Models;

use App\Communication\CommunicationBroadcastStatus;
use App\Communication\CommunicationChannel;
use App\Communication\CommunicationKind;
use Database\Factories\CommunicationBroadcastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
#[Hidden(['idempotency_key_hash'])]
class CommunicationBroadcast extends Model
{
    /** @use HasFactory<CommunicationBroadcastFactory> */
    use HasFactory, HasUlids;

    protected $attributes = ['status' => CommunicationBroadcastStatus::Draft->value];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(CommunicationAudience::class, 'communication_audience_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class);
    }

    protected function casts(): array
    {
        return [
            'kind' => CommunicationKind::class,
            'channel' => CommunicationChannel::class,
            'status' => CommunicationBroadcastStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'prepared_at' => 'immutable_datetime',
        ];
    }
}
