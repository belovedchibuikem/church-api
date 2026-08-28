<?php

namespace App\Models;

use Database\Factories\ScopeAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'role_assignment_id',
    'assigned_by_user_id',
    'scope_type',
    'scope_key',
])]
class ScopeAssignment extends Model
{
    /** @use HasFactory<ScopeAssignmentFactory> */
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

    public function roleAssignment(): BelongsTo
    {
        return $this->belongsTo(RoleAssignment::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
