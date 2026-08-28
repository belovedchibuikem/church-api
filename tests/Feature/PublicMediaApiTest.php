<?php

namespace Tests\Feature;

use App\Demo\DemoPngFactory;
use App\Files\FileAssetClassification;
use App\Files\FileAssetStatus;
use App\Models\FileAsset;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMediaApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_streams_available_public_images_inline(): void
    {
        Storage::fake('local');
        $bytes = DemoPngFactory::make([56, 18, 184], 48, 32);
        $asset = FileAsset::factory()->available()->create([
            'classification' => FileAssetClassification::Public,
            'detected_mime_type' => 'image/png',
            'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'metadata' => ['original_filename' => 'hero.png'],
        ]);
        Storage::disk('local')->put($asset->object_key, $bytes);

        $response = $this->get("/api/v1/media/{$asset->public_id}");

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertSame($bytes, $response->streamedContent());
    }

    public function test_does_not_stream_internal_or_unavailable_files(): void
    {
        Storage::fake('local');
        $internal = FileAsset::factory()->available()->create([
            'classification' => FileAssetClassification::Internal,
            'detected_mime_type' => 'image/png',
        ]);
        $quarantined = FileAsset::factory()->create([
            'classification' => FileAssetClassification::Public,
            'status' => FileAssetStatus::Quarantined,
            'detected_mime_type' => 'image/png',
        ]);

        $this->getJson("/api/v1/media/{$internal->public_id}")->assertNotFound();
        $this->getJson("/api/v1/media/{$quarantined->public_id}")->assertNotFound();
    }
}
