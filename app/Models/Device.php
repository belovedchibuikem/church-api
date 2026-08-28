<?php

namespace App\Models;

use App\Identity\UserAccountStatus;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'identifier_hash',
    'label',
    'device_type',
    'platform',
    'app_version',
    'first_seen_at',
    'last_seen_at',
])]
#[Hidden(['identifier_hash'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function securitySessions(): HasMany
    {
        return $this->hasMany(SecuritySession::class);
    }

    public function mobileAccessTokens(): HasMany
    {
        return $this->hasMany(MobileAccessToken::class);
    }

    public function mobileRefreshTokens(): HasMany
    {
        return $this->hasMany(MobileRefreshToken::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    #[Scope]
    protected function usable(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->whereHas('user', fn (Builder $userQuery): Builder => $userQuery
                ->where('account_status', UserAccountStatus::Active->value)
                ->whereNull('suspended_at'));
    }
}
