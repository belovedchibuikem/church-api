<?php

namespace App\Models;

use App\Press\PressContributorRole;
use Database\Factories\PressPublicationContributorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class PressPublicationContributor extends Model
{
    /** @use HasFactory<PressPublicationContributorFactory> */
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

    public function publication(): BelongsTo
    {
        return $this->belongsTo(PressPublication::class, 'press_publication_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['role' => PressContributorRole::class];
    }
}
