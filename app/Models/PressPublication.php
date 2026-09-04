<?php

namespace App\Models;

use App\Media\HasMedia;
use App\Press\PressIsbnType;
use App\Press\PressPublicationAvailability;
use App\Press\PressPublicationFormat;
use App\Press\PressPublicationStatus;
use App\Press\PressPublicationType;
use App\Press\PressPublicationVisibility;
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
    'language_code', 'page_count', 'category', 'description',     'cover_file_asset_id',
    'content_file_asset_id', 'content_source_url', 'price_minor', 'currency_code', 'format', 'summary', 'slug',
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
        'publication_type' => PressPublicationType::Book->value,
        'visibility' => PressPublicationVisibility::Public->value,
        'featured' => false,
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

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_user_id');
    }

    public function contributors(): HasMany
    {
        return $this->hasMany(PressPublicationContributor::class)->orderBy('sort_order')->orderBy('id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(PressPublicationTransition::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PressTranslation::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(PressPublicationAsset::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PressPublicationReview::class);
    }

    public function publicationType(): PressPublicationType
    {
        return $this->publication_type instanceof PressPublicationType
            ? $this->publication_type
            : PressPublicationType::tryFrom((string) $this->publication_type) ?? PressPublicationType::Book;
    }

    public function visibilityEnum(): PressPublicationVisibility
    {
        return $this->visibility instanceof PressPublicationVisibility
            ? $this->visibility
            : PressPublicationVisibility::tryFrom((string) $this->visibility) ?? PressPublicationVisibility::Public;
    }

    public function requiresIsbnToPublish(): bool
    {
        return $this->publicationType()->requiresIsbnToPublish();
    }

    public function isPubliclyListed(): bool
    {
        return $this->status instanceof PressPublicationStatus
            && $this->status->isPubliclyListable()
            && $this->availability === PressPublicationAvailability::Available
            && $this->published_at !== null
            && $this->archived_at === null
            && $this->visibilityEnum()->isPublicCatalogue();
    }

    public function hasUnreadyRequiredAssets(): bool
    {
        return $this->assets()
            ->where('is_required', true)
            ->where('is_current', true)
            ->where('processing_status', '!=', 'ready')
            ->exists();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'format' => PressPublicationFormat::class,
            'publication_type' => PressPublicationType::class,
            'availability' => PressPublicationAvailability::class,
            'visibility' => PressPublicationVisibility::class,
            'status' => PressPublicationStatus::class,
            'isbn_type' => PressIsbnType::class,
            'type_metadata' => 'array',
            'featured' => 'boolean',
            'publication_date' => 'immutable_date',
            'copyright_year' => 'integer',
            'page_count' => 'integer',
            'price_minor' => 'integer',
            'status_changed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'distributed_at' => 'immutable_datetime',
            'scheduled_publish_at' => 'immutable_datetime',
            'scheduled_unpublish_at' => 'immutable_datetime',
            'unpublished_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
