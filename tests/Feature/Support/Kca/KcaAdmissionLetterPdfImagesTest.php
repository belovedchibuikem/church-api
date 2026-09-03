<?php

namespace Tests\Feature\Support\Kca;

use App\Files\FileAssetStatus;
use App\Kca\KcaApplicationState;
use App\Models\FileAsset;
use App\Models\KcaAdmissionLetter;
use App\Models\KcaApplication;
use App\Models\KcaGovernanceConfiguration;
use App\Models\Person;
use App\Support\Kca\KcaAdmissionLetterPdfRenderer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KcaAdmissionLetterPdfImagesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pdf_embeds_logo_and_signature_images(): void
    {
        Storage::fake('local');

        $logoBytes = $this->makeJpegBytes(80, 80, [20, 80, 160]);
        $signatureBytes = $this->makeJpegBytes(200, 60, [10, 10, 10]);

        $letterhead = FileAsset::factory()->available()->create([
            'purpose' => 'kca_admission_letterhead',
            'detected_mime_type' => 'image/jpeg',
            'byte_size' => strlen($logoBytes),
            'sha256' => hash('sha256', $logoBytes),
            'object_key' => 'kca/test-logo.jpg',
            'status' => FileAssetStatus::Pending,
        ]);
        $signature = FileAsset::factory()->available()->create([
            'purpose' => 'kca_admission_signature',
            'detected_mime_type' => 'image/jpeg',
            'byte_size' => strlen($signatureBytes),
            'sha256' => hash('sha256', $signatureBytes),
            'object_key' => 'kca/test-signature.jpg',
            'status' => FileAssetStatus::Pending,
        ]);

        Storage::disk('local')->put($letterhead->object_key, $logoBytes);
        Storage::disk('local')->put($signature->object_key, $signatureBytes);

        $person = Person::factory()->withProfile()->create();
        $application = KcaApplication::factory()->create([
            'person_id' => $person->getKey(),
            'status' => KcaApplicationState::Accepted,
        ]);
        KcaGovernanceConfiguration::factory()->create([
            'admission_signer_name' => 'Babatope Stephen Agbaje',
            'admission_signer_title' => 'Provost',
            'admission_letterhead_file_asset_id' => $letterhead->getKey(),
            'admission_signature_file_asset_id' => $signature->getKey(),
        ]);

        $letter = KcaAdmissionLetter::factory()->create([
            'kca_application_id' => $application->getKey(),
            'signer_name' => 'Babatope Stephen Agbaje',
            'signer_title' => 'Provost',
            'letterhead_file_asset_id' => $letterhead->getKey(),
            'signature_file_asset_id' => $signature->getKey(),
            'letter_body' => "Dear Applicant,\n\nWelcome to KCA.\n\nYours faithfully,\n\nBabatope Stephen Agbaje\n\nProvost",
        ]);

        // Simulate the constrained eager-load that previously broke PDF images.
        $letter->load([
            'application.person.profile',
            'letterheadFile:id,public_id',
            'signatureFile:id,public_id',
        ]);

        $pdf = $this->app->make(KcaAdmissionLetterPdfRenderer::class)->render($letter);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('/Logo', $pdf);
        $this->assertStringContainsString('/Signature1', $pdf);
        $this->assertStringContainsString('/Filter /DCTDecode', $pdf);
        $this->assertSame(FileAssetStatus::Available, $letterhead->fresh()->status);
        $this->assertSame(FileAssetStatus::Available, $signature->fresh()->status);
    }

    private function makeJpegBytes(int $width, int $height, array $rgb): string
    {
        $this->assertTrue(function_exists('imagecreatetruecolor'));
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($image, 0, 0, $width, $height, $color);
        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);
        $bytes = ob_get_clean();
        $this->assertIsString($bytes);

        return $bytes;
    }
}
