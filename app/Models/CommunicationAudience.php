<?php

namespace App\Models;

use Database\Factories\CommunicationAudienceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
class CommunicationAudience extends Model
{
    /** @use HasFactory<CommunicationAudienceFactory> */
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

    public function rules(): HasMany
    {
        return $this->hasMany(CommunicationAudienceRule::class);
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(CommunicationBroadcast::class);
    }
}
