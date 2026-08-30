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
    'avatar_file_asset_id',
])]
class PersonProfile extends Model
{
    /** @use HasFactory<PersonProfileFactory> */
    use HasFactory;

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function avatarFileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'avatar_file_asset_id');
    }
}
