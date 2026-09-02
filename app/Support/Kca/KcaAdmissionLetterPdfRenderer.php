<?php

namespace App\Support\Kca;

use App\Files\Queries\OpenFileAssetStreamQuery;
use App\Models\FileAsset;
use App\Models\KcaAdmissionLetter;
use App\Support\Identity\PersonDisplayName;
use App\Support\Pdf\SimplePdfDocument;

class KcaAdmissionLetterPdfRenderer
{
    private const PAGE_WIDTH = 612;

    private const PAGE_HEIGHT = 792;

    public function __construct(
        private readonly OpenFileAssetStreamQuery $fileAssetStreams,
    ) {}

    public function render(KcaAdmissionLetter $letter): string
    {
        $letter->loadMissing([
            'application.person.profile',
            'letterheadFile',
            'signatureFile',
        ]);

        $pdf = new SimplePdfDocument;
        $operations = [];

        if ($letter->letterheadFile instanceof FileAsset) {
            $jpeg = $this->jpegBytes($letter->letterheadFile);
            if ($jpeg !== null) {
                $info = getimagesizefromstring($jpeg);
                if (is_array($info)) {
                    $pdf->addJpegImage('Letterhead', $jpeg, (int) $info[0], (int) $info[1]);
                    $operations[] = 'q';
                    $operations[] = self::PAGE_WIDTH.' 0 0 '.self::PAGE_HEIGHT.' 0 0 cm';
                    $operations[] = '/Letterhead Do';
                    $operations[] = 'Q';
                }
            }
        }

        $applicant = PersonDisplayName::of($letter->application?->person) ?: 'Applicant';
        $reference = (string) ($letter->reference_code ?? 'Pending');
        $issuedOn = $letter->issued_at?->format('d/m/Y') ?? now()->format('d/m/Y');
        $body = trim((string) ($letter->letter_body ?? ''));
        if ($body === '') {
            $resolver = app(ResolveKcaApplicationChurchName::class);
            $body = $resolver->defaultLetterBody(
                $applicant,
                $resolver->fromApplicationData($letter->application?->application_data),
                $letter->batch_label,
            );
        }

        $y = 468;
        $operations[] = 'BT /F1 10 Tf';
        $operations[] = "72 {$y} Td (Date: ".SimplePdfDocument::escapeText($issuedOn).') Tj';
        $operations[] = '0 -16 Td (Ref: '.SimplePdfDocument::escapeText($reference).') Tj';
        $operations[] = '0 -24 Td /F1 11 Tf (Dear '.SimplePdfDocument::escapeText($applicant).',) Tj';
        $operations[] = 'ET';

        $signerName = (string) ($letter->signer_name ?? 'Provost, KCA');
        $signerTitle = (string) ($letter->signer_title ?? '');

        $y = 412;
        foreach (preg_split("/\R\R+/", $body) ?: [] as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }

            if ($paragraph === $signerName) {
                $operations[] = 'BT /F1 11 Tf 72 '.$y.' Td ('.SimplePdfDocument::escapeText($signerName).') Tj ET';
                $y -= 18;

                if ($letter->signatureFile instanceof FileAsset) {
                    $signatureJpeg = $this->jpegBytes($letter->signatureFile);
                    if ($signatureJpeg !== null) {
                        $info = getimagesizefromstring($signatureJpeg);
                        if (is_array($info)) {
                            $pdf->addJpegImage('Signature', $signatureJpeg, (int) $info[0], (int) $info[1]);
                            $drawWidth = 140;
                            $drawHeight = 48;
                            $signatureY = $y - $drawHeight;
                            $operations[] = 'q';
                            $operations[] = "{$drawWidth} 0 0 {$drawHeight} 72 {$signatureY} cm";
                            $operations[] = '/Signature Do';
                            $operations[] = 'Q';
                            $y = $signatureY - 10;
                        }
                    }
                }

                continue;
            }

            if ($paragraph === $signerTitle) {
                $operations[] = 'BT /F1 9 Tf 72 '.$y.' Td ('.SimplePdfDocument::escapeText($signerTitle).') Tj ET';
                $y -= 18;

                continue;
            }

            foreach ($this->wrapText($paragraph, 88) as $line) {
                $operations[] = 'BT /F1 10 Tf 72 '.$y.' Td ('.SimplePdfDocument::escapeText($line).') Tj ET';
                $y -= 14;
                if ($y < 170) {
                    break 2;
                }
            }
            $y -= 6;
        }

        if (
            $signerName !== ''
            && ! str_contains($body, $signerName)
        ) {
            $operations[] = 'BT /F1 11 Tf 72 '.$y.' Td ('.SimplePdfDocument::escapeText($signerName).') Tj ET';
            $y -= 18;

            if ($letter->signatureFile instanceof FileAsset) {
                $signatureJpeg = $this->jpegBytes($letter->signatureFile);
                if ($signatureJpeg !== null) {
                    $info = getimagesizefromstring($signatureJpeg);
                    if (is_array($info)) {
                        $pdf->addJpegImage('Signature', $signatureJpeg, (int) $info[0], (int) $info[1]);
                        $drawWidth = 140;
                        $drawHeight = 48;
                        $signatureY = $y - $drawHeight;
                        $operations[] = 'q';
                        $operations[] = "{$drawWidth} 0 0 {$drawHeight} 72 {$signatureY} cm";
                        $operations[] = '/Signature Do';
                        $operations[] = 'Q';
                        $y = $signatureY - 10;
                    }
                }
            }

            if ($signerTitle !== '') {
                $operations[] = 'BT /F1 9 Tf 72 '.$y.' Td ('.SimplePdfDocument::escapeText($signerTitle).') Tj ET';
            }
        }

        $pdf->addPage($operations);

        return $pdf->build();
    }

    /** @return array<int, string> */
    private function wrapText(string $text, int $maxChars): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";
            if (strlen($candidate) > $maxChars && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function jpegBytes(FileAsset $asset): ?string
    {
        try {
            $stream = $this->fileAssetStreams->handle($asset);
        } catch (\Throwable) {
            return null;
        }

        $bytes = stream_get_contents($stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $mime = strtolower((string) $asset->detected_mime_type);
        if (str_contains($mime, 'jpeg') || str_contains($mime, 'jpg')) {
            return $bytes;
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }

        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);
        $jpeg = ob_get_clean();

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }
}
