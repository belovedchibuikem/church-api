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

    public function test_pdf_embeds_full_page_letterhead_template_and_signature(): void
    {
        Storage::fake('local');

        $templateBytes = $this->makeJpegBytes(612, 792, [245, 248, 252]);
        $signatureBytes = $this->makeJpegBytes(200, 60, [10, 10, 10]);

        $letterhead = FileAsset::factory()->available()->create([
            'purpose' => 'kca_admission_letterhead',
            'detected_mime_type' => 'image/jpeg',
            'byte_size' => strlen($templateBytes),
            'sha256' => hash('sha256', $templateBytes),
            'object_key' => 'kca/test-letterhead-template.jpg',
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

        Storage::disk('local')->put($letterhead->object_key, $templateBytes);
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
        $this->assertStringContainsString('/Letterhead', $pdf);
        $this->assertStringNotContainsString('KINGDOM CHANGE AGENTS (KCA)', $pdf);
        $this->assertStringNotContainsString('Admission Office', $pdf);
        $this->assertStringContainsString('/Signature1', $pdf);
        $this->assertStringContainsString('/Filter /DCTDecode', $pdf);
        $this->assertSame(FileAssetStatus::Available, $letterhead->fresh()->status);
        $this->assertSame(FileAssetStatus::Available, $signature->fresh()->status);
    }

    public function test_pdf_formats_condensed_commitment_and_journey_sections(): void
    {
        Storage::fake('local');

        $person = Person::factory()->withProfile()->create();
        $application = KcaApplication::factory()->create([
            'person_id' => $person->getKey(),
            'status' => KcaApplicationState::Accepted,
        ]);

        $letter = KcaAdmissionLetter::factory()->create([
            'kca_application_id' => $application->getKey(),
            'signer_name' => 'Babatope Stephen Agbaje',
            'signer_title' => 'Provost',
            'letter_body' => implode("\n\n", [
                'Congratulations! You have been accepted into KCA.',
                'YOUR COMMITMENT: Attend at least 10 of 12 sessions Actively participate (Bible study, discussions, prayer, mentoring, ministry) Complete all 4 written assignments Serve in at least 2 church departments before graduation Engage with your assigned mentor Uphold Christian conduct respect, humility, integrity, discipline, love',
                '12-SESSION JOURNEY: The Call of the King Born into the Kingdom Living as a Child of the King Walking with the Holy Spirit At the King\'s Feet Becoming Like Jesus Every Disciple Is a Servant The Church: God\'s Family on Mission Holiness in a Compromised World Sharing the Gospel Kingdom Influence Becoming a Kingdom Change Agent',
                'DECLARATION: I will follow, grow in, serve, and represent Christ. I will influence my generation and help others follow Him. I AM A KINGDOM CHANGE AGENT.',
                "Yours faithfully,\n\nBabatope Stephen Agbaje\n\nProvost",
            ]),
        ]);

        $pdf = $this->app->make(KcaAdmissionLetterPdfRenderer::class)->render($letter);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('YOUR COMMITMENT', $pdf);
        $this->assertStringContainsString('12-SESSION JOURNEY', $pdf);
        $this->assertStringContainsString('Attend at least 10 of 12 sessions', $pdf);
        $this->assertStringContainsString('The Call of the King', $pdf);
        $this->assertStringContainsString('I AM A KINGDOM CHANGE AGENT.', $pdf);
        $this->assertMatchesRegularExpression('/\d+\.\d+ Tw/', $pdf);
    }

    public function test_pdf_polishes_run_on_sentences_in_letter_body(): void
    {
        Storage::fake('local');

        $person = Person::factory()->withProfile()->create();
        $application = KcaApplication::factory()->create([
            'person_id' => $person->getKey(),
            'status' => KcaApplicationState::Accepted,
        ]);

        $letter = KcaAdmissionLetter::factory()->create([
            'kca_application_id' => $application->getKey(),
            'signer_name' => 'Babatope Stephen Agbaje',
            'signer_title' => 'Provost',
            'letter_body' => implode("\n\n", [
                'Congratulations! You have been accepted into the Kingdom Change Agents (KCA) Youth Discipleship Training Programme of The Family House of God International a journey to grow in Christ.',
                'This admission is only the beginning success is measured not by a certificate, but by becoming more like Christ.',
                "Yours faithfully,\n\nBabatope Stephen Agbaje\n\nProvost",
            ]),
        ]);

        $pdf = $this->app->make(KcaAdmissionLetterPdfRenderer::class)->render($letter);

        $this->assertStringContainsString('International, a journey', $pdf);
        $this->assertStringContainsString('beginning. Success', $pdf);
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
