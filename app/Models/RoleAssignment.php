<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RoleAssignmentFactory;
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
    'role_id',
    'assigned_by_user_id',
    'assigned_at',
    'expires_at',
])]
class RoleAssignment extends Model
{
    /** @use HasFactory<RoleAssignmentFactory> */
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function scopeAssignments(): HasMany
    {
        return $this->hasMany(ScopeAssignment::class);
    }

    public function accessDecisions(): HasMany
    {
        return $this->hasMany(AccessDecision::class, 'matched_role_assignment_id');
    }

    #[Scope]
    protected function active(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now()->utc();

        return $query
            ->where('assigned_at', '<=', $at)
            ->whereNull('revoked_at')
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $at);
            });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
