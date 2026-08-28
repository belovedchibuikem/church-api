<?php

namespace App\Models;

use App\Reporting\AlertSeverity;
use Database\Factories\AlertRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
#[Hidden(['configuration'])]
class AlertRule extends Model
{
    /** @use HasFactory<AlertRuleFactory> */
    use HasFactory, HasUlids;

    protected $attributes = ['is_active' => false];

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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(AlertOccurrence::class);
    }

    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'configuration' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }
}
