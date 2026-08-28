<?php

namespace App\Models;

use Database\Factories\KcaYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'starts_on', 'ends_on'])]
class KcaYear extends Model
{
    /** @use HasFactory<KcaYearFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(KcaCohort::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(KcaEnrollment::class);
    }

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }
}
