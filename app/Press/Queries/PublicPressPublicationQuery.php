<?php

namespace App\Press\Queries;

use App\Models\PressPublication;
use App\Press\PressPublicationAvailability;
use App\Press\PressPublicationStatus;
use App\Press\PressTranslationStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicPressPublicationQuery
{
    /**
     * @param  array{language?: string, category?: string, format?: string}  $filters
     * @return LengthAwarePaginator<int, PressPublication>
     */
    public function paginate(array $filters, string $sort, int $perPage): LengthAwarePaginator
    {
        $query = $this->publiclyAvailableQuery();

        $query
            ->when($filters['language'] ?? null, fn (Builder $query, string $language): Builder => $query->where('language_code', $language))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category): Builder => $query->where('category', $category))
            ->when($filters['format'] ?? null, fn (Builder $query, string $format): Builder => $query->where('format', $format));

        $this->applySort($query, $sort);

        return $query->paginate($perPage)->withQueryString();
    }

    public function findByPublicIdOrFail(string $publicId): PressPublication
    {
        return $this->publiclyAvailableQuery()
            ->with(['translations' => function (HasMany $query): void {
                $query
                    ->select([
                        'id',
                        'public_id',
                        'press_publication_id',
                        'target_language_code',
                        'translated_title',
                        'translated_subtitle',
                        'translated_description',
                        'approved_at',
                    ])
                    ->where('status', PressTranslationStatus::Approved->value)
                    ->whereNotNull('approved_at')
                    ->orderBy('target_language_code');
            }])
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    /** @return Builder<PressPublication> */
    private function publiclyAvailableQuery(): Builder
    {
        return PressPublication::query()
            ->select([
                'id',
                'public_id',
                'title',
                'subtitle',
                'publisher_name',
                'edition',
                'publication_date',
                'copyright_year',
                'language_code',
                'page_count',
                'category',
                'description',
                'format',
                'availability',
                'isbn',
                'isbn_type',
                'published_at',
                'cover_file_asset_id',
            ])
            ->with(['coverFileAsset', 'mediaAttachments.fileAsset'])
            ->whereIn('status', [
                PressPublicationStatus::Published->value,
                PressPublicationStatus::Distribution->value,
            ])
            ->where('availability', PressPublicationAvailability::Available->value)
            ->whereNotNull('published_at');
    }

    /** @param Builder<PressPublication> $query */
    private function applySort(Builder $query, string $sort): void
    {
        [$column, $direction] = match ($sort) {
            'publication_date' => ['publication_date', 'asc'],
            'title' => ['title', 'asc'],
            '-title' => ['title', 'desc'],
            default => ['publication_date', 'desc'],
        };

        $query->orderBy($column, $direction)->orderBy('public_id');
    }
}
