<?php

namespace App\Models;

use App\Press\PressTranslationStatus;
use Database\Factories\PressTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['translated_title', 'translated_subtitle', 'translated_description', 'translated_content'])]
#[Hidden(['idempotency_key_hash', 'request_fingerprint'])]
class PressTranslation extends Model
{
    /** @use HasFactory<PressTranslationFactory> */
    use HasFactory, HasUlids;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => PressTranslationStatus::MachineGenerated->value];

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

    public function transitions(): HasMany
    {
        return $this->hasMany(PressTranslationTransition::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PressTranslationStatus::class,
            'status_changed_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
        ];
    }
}
