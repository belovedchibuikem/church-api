<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\PressPublication;
use App\Press\PressContributorRole;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PressPublication */
class PressPublicationAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PressPublication $publication */
        $publication = $this->resource;

        return [
            'id' => $publication->public_id,
            'title' => $publication->title,
            'subtitle' => $publication->subtitle,
            'slug' => $publication->slug,
            'summary' => $publication->summary,
            'publisher_name' => $publication->publisher_name,
            'language_code' => $publication->language_code,
            'category' => $publication->category,
            'description' => $publication->description,
            'edition' => $publication->edition,
            'publication_date' => $publication->publication_date?->toDateString(),
            'copyright_year' => $publication->copyright_year,
            'page_count' => $publication->page_count,
            'format' => $publication->format->value,
            'publication_type' => $publication->publicationType()->value,
            'availability' => $publication->availability->value,
            'visibility' => $publication->visibilityEnum()->value,
            'featured' => (bool) $publication->featured,
            'status' => $publication->status->value,
            'allowed_transitions' => $publication->status->allowedTargetValues(),
            'isbn' => $publication->isbn,
            'type_metadata' => $publication->type_metadata ?? [],
            'price_minor' => $publication->price_minor,
            'currency_code' => $publication->currency_code,
            'scheduled_publish_at' => $publication->scheduled_publish_at?->utc()->toIso8601String(),
            'scheduled_unpublish_at' => $publication->scheduled_unpublish_at?->utc()->toIso8601String(),
            'published_at' => $publication->published_at?->utc()->toIso8601String(),
            'created_at' => $publication->created_at?->utc()->toIso8601String(),
            'updated_at' => $publication->updated_at?->utc()->toIso8601String(),
            'author_name' => PersonDisplayName::of(
                $publication->relationLoaded('contributors')
                    ? $publication->contributors->firstWhere('role', PressContributorRole::Author)?->person
                        ?? $publication->contributors->first()?->person
                    : null,
            ) ?: '—',
            'contributors' => $publication->relationLoaded('contributors')
                ? $publication->contributors->map(fn ($contributor): array => [
                    'id' => $contributor->public_id,
                    'person_id' => $contributor->person?->public_id,
                    'person_name' => PersonDisplayName::of($contributor->person),
                    'role' => $contributor->role->value,
                    'sort_order' => $contributor->sort_order,
                ])->all()
                : [],
            'assets' => $publication->relationLoaded('assets')
                ? $publication->assets->map(fn ($asset): array => [
                    'id' => $asset->public_id,
                    'file_asset_id' => $asset->fileAsset?->public_id,
                    'asset_format' => $asset->asset_format->value,
                    'version' => $asset->version,
                    'is_current' => $asset->is_current,
                    'is_required' => $asset->is_required,
                    'processing_status' => $asset->processing_status->value,
                    'label' => $asset->label,
                ])->all()
                : [],
            'reviews' => $publication->relationLoaded('reviews')
                ? $publication->reviews->map(fn ($review): array => [
                    'id' => $review->public_id,
                    'stage' => $review->stage->value,
                    'decision' => $review->decision->value,
                    'comments' => $review->comments,
                    'requested_changes' => $review->requested_changes,
                    'decided_at' => $review->decided_at?->utc()->toIso8601String(),
                    'reviewer_name' => PersonDisplayName::of($review->reviewer),
                ])->all()
                : [],
            'translations' => $publication->relationLoaded('translations')
                ? $publication->translations->map(fn ($translation): array => [
                    'id' => $translation->public_id,
                    'target_language_code' => $translation->target_language_code,
                    'translated_title' => $translation->translated_title,
                    'status' => $translation->status->value,
                ])->all()
                : [],
        ];
    }
}
