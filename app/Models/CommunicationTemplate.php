<?php

namespace App\Models;

use App\Communication\CommunicationChannel;
use Database\Factories\CommunicationTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'channel', 'locale', 'subject', 'body', 'created_by_user_id'])]
class CommunicationTemplate extends Model
{
    /** @use HasFactory<CommunicationTemplateFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(CommunicationBroadcast::class);
    }

    protected function casts(): array
    {
        return ['channel' => CommunicationChannel::class];
    }
}
