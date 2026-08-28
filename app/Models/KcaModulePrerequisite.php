<?php

namespace App\Models;

use App\Kca\KcaPrerequisiteRequirement;
use Database\Factories\KcaModulePrerequisiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kca_module_id', 'prerequisite_module_id', 'requirement'])]
class KcaModulePrerequisite extends Model
{
    /** @use HasFactory<KcaModulePrerequisiteFactory> */
    use HasFactory, HasUlids;

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(KcaModule::class, 'kca_module_id');
    }

    public function prerequisiteModule(): BelongsTo
    {
        return $this->belongsTo(KcaModule::class, 'prerequisite_module_id');
    }

    protected function casts(): array
    {
        return ['requirement' => KcaPrerequisiteRequirement::class];
    }
}
