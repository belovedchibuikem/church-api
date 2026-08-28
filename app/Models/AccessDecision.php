<?php

namespace App\Models;

use App\Exceptions\AccessDecisionImmutableException;
use App\Support\Authorization\AccessDecisionReason;
use Database\Factories\AccessDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'actor_user_id',
    'matched_role_assignment_id',
    'permission_code',
    'scope_type',
    'scope_key',
    'allowed',
    'reason_code',
    'correlation_id',
    'decided_at',
])]
class AccessDecision extends Model
{
    /** @use HasFactory<AccessDecisionFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function matchedRoleAssignment(): BelongsTo
    {
        return $this->belongsTo(RoleAssignment::class, 'matched_role_assignment_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed' => 'boolean',
            'reason_code' => AccessDecisionReason::class,
            'decided_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new AccessDecisionImmutableException;
        });

        static::deleting(function (): never {
            throw new AccessDecisionImmutableException;
        });
    }
}
