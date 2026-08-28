<?php

namespace App\Models;

use Database\Factories\PersonProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'given_name',
    'middle_name',
    'family_name',
    'preferred_name',
    'country',
    'region',
    'locality',
])]
class PersonProfile extends Model
{
    /** @use HasFactory<PersonProfileFactory> */
    use HasFactory;

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
