<?php

namespace App\Models;

use Database\Factories\PersonPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'locale',
    'timezone',
    'notification_channels',
])]
class PersonPreference extends Model
{
    /** @use HasFactory<PersonPreferenceFactory> */
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notification_channels' => 'array',
        ];
    }
}
