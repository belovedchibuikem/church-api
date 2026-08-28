<?php

namespace App\Models;

use App\Identity\UserAccountStatus;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, MustVerifyEmail, Notifiable;

    protected $attributes = [
        'account_status' => UserAccountStatus::Active->value,
    ];

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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'actor_user_id');
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function accessDecisions(): HasMany
    {
        return $this->hasMany(AccessDecision::class, 'actor_user_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function securitySessions(): HasMany
    {
        return $this->hasMany(SecuritySession::class);
    }

    public function mfaMethods(): HasMany
    {
        return $this->hasMany(MfaMethod::class);
    }

    public function mobileAccessTokens(): HasMany
    {
        return $this->hasMany(MobileAccessToken::class);
    }

    public function mobileRefreshTokens(): HasMany
    {
        return $this->hasMany(MobileRefreshToken::class);
    }

    public function isSuspended(): bool
    {
        return $this->account_status === UserAccountStatus::Suspended;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new QueuedResetPassword((string) $token));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_status' => UserAccountStatus::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'reactivated_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
        ];
    }

    #[Scope]
    protected function suspended(Builder $query): Builder
    {
        return $query->where('account_status', UserAccountStatus::Suspended->value);
    }
}
