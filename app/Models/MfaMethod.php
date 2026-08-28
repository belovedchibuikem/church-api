<?php

namespace App\Models;

use App\Identity\UserAccountStatus;
use Database\Factories\MfaMethodFactory;
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
    'method_type',
    'label',
    'secret_hash',
    'encrypted_secret',
])]
#[Hidden(['secret_hash', 'encrypted_secret'])]
class MfaMethod extends Model
{
    /** @use HasFactory<MfaMethodFactory> */
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

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(MfaRecoveryCode::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret_hash' => 'hashed',
            'encrypted_secret' => 'encrypted',
            'verified_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
            'last_totp_counter' => 'integer',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    #[Scope]
    protected function usable(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->whereNotNull('verified_at')
            ->whereHas('user', fn (Builder $userQuery): Builder => $userQuery
                ->where('account_status', UserAccountStatus::Active->value)
                ->whereNull('suspended_at'));
    }
}
