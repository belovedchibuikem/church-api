<?php

namespace App\Models;

use Database\Factories\MobileAccessTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'security_session_id',
    'device_id',
    'family_id',
    'token_hash',
    'expires_at',
])]
#[Hidden(['token_hash'])]
class MobileAccessToken extends Model
{
    /** @use HasFactory<MobileAccessTokenFactory> */
    use HasFactory, HasUlids;

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function securitySession(): BelongsTo
    {
        return $this->belongsTo(SecuritySession::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
