<?php

namespace App\Models;

use App\Media\HasMedia;
use App\Press\PressIsbnType;
use App\Press\PressPublicationAvailability;
use App\Press\PressPublicationFormat;
use App\Press\PressPublicationStatus;
use Database\Factories\PressPublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'subtitle', 'publisher_name', 'edition', 'publication_date', 'copyright_year',
    'language_code', 'page_count', 'category', 'description', 'cover_file_asset_id',
    'content_file_asset_id', 'price_minor', 'currency_code', 'format',
])]
#[Hidden(['idempotency_key_hash', 'request_fingerprint'])]
class PressPublication extends Model
{
    /** @use HasFactory<PressPublicationFactory> */
    use HasFactory, HasMedia, HasUlids;

    /** @var array<string, mixed> */
    protected $attributes = [
        'availability' => PressPublicationAvailability::Unavailable->value,
        'status' => PressPublicationStatus::Manuscript->value,
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

    public function coverFileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'cover_file_asset_id');
    }

    public function contentFileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class, 'content_file_asset_id');
    }

    public function contributors(): HasMany
    {
        return $this->hasMany(PressPublicationContributor::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(PressPublicationTransition::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PressTranslation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'format' => PressPublicationFormat::class,
            'availability' => PressPublicationAvailability::class,
            'status' => PressPublicationStatus::class,
            'isbn_type' => PressIsbnType::class,
            'publication_date' => 'immutable_date',
            'copyright_year' => 'integer',
            'page_count' => 'integer',
            'price_minor' => 'integer',
            'status_changed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'distributed_at' => 'immutable_datetime',
        ];
    }
}
