<?php

namespace App\Models;

use App\Safeguarding\MinorStatus;
use Database\Factories\ChildProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'date_of_birth',
    'minor_status',
    'direct_communication_restricted',
    'media_use_restricted',
])]
#[Hidden(['date_of_birth'])]
class ChildProfile extends Model
{
    /** @use HasFactory<ChildProfileFactory> */
    use HasFactory, HasUlids;

    /** @return array<int, string> */
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'encrypted',
            'minor_status' => MinorStatus::class,
            'direct_communication_restricted' => 'boolean',
            'media_use_restricted' => 'boolean',
        ];
    }
}
