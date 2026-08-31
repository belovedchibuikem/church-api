<?php

namespace App\Models;

use App\Administration\AdminWorkItemPriority;
use App\Administration\AdminWorkItemStatus;
use Database\Factories\AdminWorkItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'title',
    'body',
    'status',
    'priority',
    'due_at',
    'assigned_to_user_id',
    'created_by_user_id',
    'closed_at',
])]
class AdminWorkItem extends Model
{
    /** @use HasFactory<AdminWorkItemFactory> */
    use HasFactory, HasUlids;

    protected $attributes = [
        'status' => 'open',
        'priority' => 'normal',
    ];

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AdminWorkItemStatus::class,
            'priority' => AdminWorkItemPriority::class,
            'due_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
