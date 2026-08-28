<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
class DemoDataset extends Model
{
    public const KEY = 'family-house-connect';

    public function records(): HasMany
    {
        return $this->hasMany(DemoDatasetRecord::class, 'dataset_key', 'dataset_key');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'seeded_at' => 'immutable_datetime',
            'summary' => 'array',
        ];
    }
}
