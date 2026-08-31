<?php

namespace App\Models;

use App\Identity\UserAccountStatus;
use Database\Factories\SecuritySessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'device_id',
    'started_at',
    'last_seen_at',
    'expires_at',
    'last_ip',
    'last_country',
])]
class SecuritySession extends Model
{
    /** @use HasFactory<SecuritySessionFactory> */
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

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function mfaMethod(): BelongsTo
    {
        return $this->belongsTo(MfaMethod::class);
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
            'started_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'mfa_verified_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    #[Scope]
    protected function usable(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function (Builder $expirationQuery): void {
                $expirationQuery
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereHas('user', fn (Builder $userQuery): Builder => $userQuery
                ->where('account_status', UserAccountStatus::Active->value)
                ->whereNull('suspended_at'))
            ->where(function (Builder $deviceQuery): void {
                $deviceQuery
                    ->whereNull('device_id')
                    ->orWhereHas('device', fn (Builder $activeDeviceQuery): Builder => $activeDeviceQuery
                        ->whereNull('revoked_at'));
            });
    }
}
