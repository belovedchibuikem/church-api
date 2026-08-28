<?php

namespace App\Models;

use App\Platform\ConfigurationClassification;
use App\Platform\ConfigurationValueType;
use Database\Factories\PlatformConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'key',
    'value_type',
    'classification',
    'environment',
    'scope_type',
    'scope_key',
    'context_hash',
    'stored_value',
])]
#[Hidden(['stored_value'])]
class PlatformConfiguration extends Model
{
    /** @use HasFactory<PlatformConfigurationFactory> */
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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => ConfigurationClassification::class,
            'value_type' => ConfigurationValueType::class,
        ];
    }
}
