<?php

namespace App\Models;

use Database\Factories\KcaModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'title', 'sequence', 'is_active'])]
class KcaModule extends Model
{
    /** @use HasFactory<KcaModuleFactory> */
    use HasFactory, HasUlids;

    protected $attributes = ['is_active' => true];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(KcaLesson::class);
    }

    public function prerequisites(): HasMany
    {
        return $this->hasMany(KcaModulePrerequisite::class);
    }

    public function lecturerAssignments(): HasMany
    {
        return $this->hasMany(KcaLecturerAssignment::class);
    }

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'is_active' => 'boolean'];
    }
}
