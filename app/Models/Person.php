<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
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

    public function profile(): HasOne
    {
        return $this->hasOne(PersonProfile::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
