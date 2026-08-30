<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'church_id',
    'client_person_id',
    'counselor_person_id',
    'case_type',
    'status',
    'summary',
    'opened_at',
    'closed_at',
])]
#[Hidden(['summary'])]
class CounsellingCase extends Model
{
    use HasUlids;

    protected $attributes = [
        'case_type' => 'general',
        'status' => 'open',
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

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'client_person_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'counselor_person_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'summary' => 'encrypted',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
