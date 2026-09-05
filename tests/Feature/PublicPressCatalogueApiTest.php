<?php

namespace Tests\Feature;

use App\Models\PressPublication;
use App\Models\PressTranslation;
use App\Press\PressPublicationAvailability;
use App\Press\PressPublicationFormat;
use App\Press\PressPublicationStatus;
use App\Press\PressTranslationStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PublicPressCatalogueApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_catalogue_is_paginated_filtered_sorted_and_limited_to_publicly_available_publications(): void
    {
        $alpha = $this->publicPublication([
            'title' => 'Alpha Publication',
            'language_code' => 'en',
            'publication_date' => '2025-01-10',
        ]);
        $this->publicPublication([
            'title' => 'Zulu Publication',
            'language_code' => 'en',
            'publication_date' => '2026-01-10',
            'status' => PressPublicationStatus::Distribution,
        ]);
        $this->publicPublication([
            'title' => 'French Publication',
            'language_code' => 'fr',
        ]);
        PressPublication::factory()->create([
            'title' => 'Private Manuscript',
            'language_code' => 'en',
            'availability' => PressPublicationAvailability::Available,
            'status' => PressPublicationStatus::Manuscript,
            'published_at' => null,
            'price_minor' => 9900,
            'currency_code' => 'USD',
        ]);
        PressPublication::factory()->create([
            'title' => 'Unavailable Publication',
            'language_code' => 'en',
            'availability' => PressPublicationAvailability::Unavailable,
            'status' => PressPublicationStatus::Published,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/press/publications?filter[language]=en&sort=title&per_page=1');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $alpha->public_id)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.api_version', 'v1');

        $this->assertSame([
            'availability',
            'category',
            'content_source_url',
            'copyright_year',
            'description',
            'edition',
            'format',
            'has_download',
            'id',
            'image_url',
            'isbn',
            'isbn_type',
            'language',
            'page_count',
            'publication_date',
            'publication_type',
            'published_at',
            'publisher',
            'slug',
            'subtitle',
            'summary',
            'title',
            'type_metadata',
        ], $this->sortedKeys($response->json('data.0')));
    }

    public function test_detail_exposes_only_approved_translation_metadata_and_no_private_or_commercial_fields(): void
    {
        $publication = $this->publicPublication([
            'price_minor' => 1599,
            'currency_code' => 'USD',
            'cover_file_asset_id' => null,
            'content_file_asset_id' => null,
        ]);
        $approved = PressTranslation::factory()->create([
            'press_publication_id' => $publication->getKey(),
            'target_language_code' => 'fr',
            'translated_title' => 'Titre approuve',
            'translated_content' => 'Full translated manuscript must remain private.',
            'status' => PressTranslationStatus::Approved,
            'approved_at' => now(),
        ]);
        PressTranslation::factory()->create([
            'press_publication_id' => $publication->getKey(),
            'target_language_code' => 'es',
            'translated_title' => 'Machine draft',
            'translated_content' => 'Unreviewed content.',
            'status' => PressTranslationStatus::MachineGenerated,
            'approved_at' => null,
        ]);

        $response = $this->getJson("/api/v1/press/publications/{$publication->public_id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $publication->public_id)
            ->assertJsonCount(1, 'data.translations')
            ->assertJsonPath('data.translations.0.id', $approved->public_id)
            ->assertJsonPath('data.translations.0.language', 'fr');

        $publicationData = $response->json('data');
        $this->assertArrayNotHasKey('price_minor', $publicationData);
        $this->assertArrayNotHasKey('currency_code', $publicationData);
        $this->assertArrayNotHasKey('cover_file_asset_id', $publicationData);
        $this->assertArrayNotHasKey('content_file_asset_id', $publicationData);
        $this->assertArrayNotHasKey('status', $publicationData);
        $this->assertArrayNotHasKey('translated_content', $publicationData['translations'][0]);
        $this->assertSame([
            'approved_at',
            'description',
            'id',
            'language',
            'subtitle',
            'title',
        ], $this->sortedKeys($publicationData['translations'][0]));
    }

    public function test_published_titles_appear_in_the_catalogue_before_distribution(): void
    {
        $publication = $this->publicPublication([
            'title' => 'Newly Published Title',
            'status' => PressPublicationStatus::Published,
        ]);

        $this->getJson('/api/v1/press/publications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $publication->public_id);
    }

    public function test_unpublished_unavailable_and_malformed_public_ids_share_the_normalized_not_found_error(): void
    {
        $manuscript = PressPublication::factory()->create([
            'status' => PressPublicationStatus::Manuscript,
            'availability' => PressPublicationAvailability::Available,
            'published_at' => null,
        ]);

        foreach ([
            "/api/v1/press/publications/{$manuscript->public_id}",
            '/api/v1/press/publications/not-a-ulid',
        ] as $url) {
            $this->getJson($url)
                ->assertNotFound()
                ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
                ->assertJsonMissingPath('data');
        }
    }

    public function test_catalogue_rejects_unknown_filters_sorts_and_query_parameters(): void
    {
        $response = $this->getJson('/api/v1/press/publications?filter[price]=free&sort=created_at&include=manuscript');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['fields' => [
                'filter',
                'sort',
                'include',
            ]]]]);
    }

    /** @param array<string, mixed> $attributes */
    private function publicPublication(array $attributes = []): PressPublication
    {
        return PressPublication::factory()->create([
            'format' => PressPublicationFormat::Print,
            'availability' => PressPublicationAvailability::Available,
            'status' => PressPublicationStatus::Published,
            'publication_date' => '2026-01-01',
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function sortedKeys(array $data): array
    {
        $keys = array_keys($data);
        sort($keys);

        return $keys;
    }
}
