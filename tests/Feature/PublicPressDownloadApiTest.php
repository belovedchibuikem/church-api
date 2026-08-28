<?php

namespace Tests\Feature;

use App\Files\FileAssetStatus;
use App\Models\FileAsset;
use App\Models\PressPublication;
use App\Press\PressPublicationAvailability;
use App\Press\PressPublicationFormat;
use App\Press\PressPublicationStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicPressDownloadApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_published_publication_streams_the_available_content_asset(): void
    {
        Storage::fake('local');
        $contents = "%PDF-1.4 publication body\n";
        $asset = $this->availableContentAsset($contents, [
            'detected_mime_type' => 'application/pdf',
            'metadata' => ['original_filename' => 'Hope and Faith.pdf'],
        ]);
        $publication = $this->publicPublication([
            'title' => 'Hope and Faith',
            'content_file_asset_id' => $asset->getKey(),
        ]);

        $response = $this->get("/api/v1/press/publications/{$publication->public_id}/download");

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('Hope and Faith.pdf');
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_download_falls_back_to_a_sanitized_title_when_original_filename_is_missing(): void
    {
        Storage::fake('local');
        $contents = 'plain publication';
        $asset = $this->availableContentAsset($contents, [
            'detected_mime_type' => 'text/plain',
            'metadata' => [],
        ]);
        $publication = $this->publicPublication([
            'title' => 'Living Hope: Volume 1',
            'content_file_asset_id' => $asset->getKey(),
        ]);

        $response = $this->get("/api/v1/press/publications/{$publication->public_id}/download");

        $response
            ->assertOk()
            ->assertDownload('Living Hope_ Volume 1');
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_download_sanitizes_unsafe_original_filenames(): void
    {
        Storage::fake('local');
        $contents = 'safe payload';
        $asset = $this->availableContentAsset($contents, [
            'metadata' => ['original_filename' => '../../evil<script>.pdf'],
        ]);
        $publication = $this->publicPublication([
            'content_file_asset_id' => $asset->getKey(),
        ]);

        $response = $this->get("/api/v1/press/publications/{$publication->public_id}/download");

        $response
            ->assertOk()
            ->assertDownload('evil_script_.pdf');
        $this->assertStringNotContainsString('<', (string) $response->headers->get('content-disposition'));
        $this->assertStringNotContainsString('..', (string) $response->headers->get('content-disposition'));
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_missing_unavailable_and_unpublished_content_share_the_normalized_not_found_error(): void
    {
        Storage::fake('local');
        $availableAsset = $this->availableContentAsset('should not leak');
        $quarantinedAsset = FileAsset::factory()->create([
            'status' => FileAssetStatus::Quarantined,
        ]);
        Storage::disk('local')->put($quarantinedAsset->object_key, 'quarantined');

        $withoutAsset = $this->publicPublication([
            'content_file_asset_id' => null,
        ]);
        $withUnavailableAsset = $this->publicPublication([
            'content_file_asset_id' => $quarantinedAsset->getKey(),
        ]);
        $unpublished = PressPublication::factory()->create([
            'format' => PressPublicationFormat::Pdf,
            'availability' => PressPublicationAvailability::Available,
            'status' => PressPublicationStatus::Manuscript,
            'published_at' => null,
            'content_file_asset_id' => $availableAsset->getKey(),
        ]);

        foreach ([
            "/api/v1/press/publications/{$withoutAsset->public_id}/download",
            "/api/v1/press/publications/{$withUnavailableAsset->public_id}/download",
            "/api/v1/press/publications/{$unpublished->public_id}/download",
            '/api/v1/press/publications/not-a-ulid/download',
        ] as $url) {
            $this->getJson($url)
                ->assertNotFound()
                ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
                ->assertJsonMissingPath('data');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function availableContentAsset(string $contents, array $attributes = []): FileAsset
    {
        $asset = FileAsset::factory()->available()->create([
            'detected_mime_type' => 'text/plain',
            'byte_size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            ...$attributes,
        ]);
        Storage::disk('local')->put($asset->object_key, $contents);

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publicPublication(array $attributes = []): PressPublication
    {
        return PressPublication::factory()->create([
            'format' => PressPublicationFormat::Pdf,
            'availability' => PressPublicationAvailability::Available,
            'status' => PressPublicationStatus::Published,
            'publication_date' => '2026-01-01',
            'published_at' => now(),
            ...$attributes,
        ]);
    }
}
