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

    private const MARGIN_X = 43;

    private const CONTENT_TOP_Y = 562;

    private const CONTENT_BOTTOM_Y = 96;

    private const BODY_FONT_SIZE = 10;

    private const HEADING_FONT_SIZE = 11;

    private const LINE_HEIGHT = 13;

    private const HEADING_LINE_HEIGHT = 16;

    private int $signatureImageCounter = 0;

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
        $this->signatureImageCounter = 0;
        $operations = $this->letterheadOperations($pdf, $letter->letterheadFile);

        $body = trim((string) ($letter->letter_body ?? ''));
        if ($body === '') {
            $resolver = app(ResolveKcaApplicationChurchName::class);
            $applicant = PersonDisplayName::of($letter->application?->person) ?: 'Applicant';
            $body = $resolver->defaultLetterBody(
                $applicant,
                $resolver->fromApplicationData($letter->application?->application_data),
                $letter->batch_label,
            );
        }

        $referenceCode = trim((string) ($letter->reference_code ?? ''));
        $body = SyncKcaAdmissionLetterReference::inBody($body, $referenceCode);

        $hasLetterhead = $letter->letterheadFile instanceof FileAsset && $operations !== [];
        $structured = $this->isStructuredTemplate($body);
        $signerName = trim((string) ($letter->signer_name ?? ''));
        $signerTitle = trim((string) ($letter->signer_title ?? ''));
        $signatureInserted = false;
        $y = $hasLetterhead || $structured ? self::CONTENT_TOP_Y : 468;

        if ($hasLetterhead && ! $structured) {
            $applicant = PersonDisplayName::of($letter->application?->person) ?: 'Applicant';
            $reference = $referenceCode !== '' ? $referenceCode : 'Pending';
            $issuedOn = $letter->issued_at?->format('d/m/Y') ?? now()->format('d/m/Y');
            $operations = array_merge($operations, $this->simplePreamble($issuedOn, $reference, $applicant));
            $y = 412;
        } elseif (! $hasLetterhead && ! $structured) {
            $applicant = PersonDisplayName::of($letter->application?->person) ?: 'Applicant';
            $reference = (string) ($letter->reference_code ?? 'Pending');
            $issuedOn = $letter->issued_at?->format('d/m/Y') ?? now()->format('d/m/Y');
            $operations = array_merge($operations, $this->simplePreamble($issuedOn, $reference, $applicant));
            $y = 412;
        }

        foreach (preg_split("/\R\R+/", $body) ?: [] as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '' || $this->shouldStopRendering($paragraph)) {
                break;
            }

            if ($hasLetterhead && $this->shouldSkipLetterheadBannerBlock($paragraph)) {
                continue;
            }

            if ($hasLetterhead && ! $structured && $this->shouldSkipOverlayPreambleLine($paragraph)) {
                continue;
            }

            if ($this->isSectionHeading($paragraph)) {
                $y -= 4;
                foreach ($this->renderLines($paragraph, self::HEADING_FONT_SIZE, self::HEADING_LINE_HEIGHT, $y) as $lineOp) {
                    $operations[] = $lineOp;
                }
                $y -= 4;

                continue;
            }

            $lines = preg_split("/\R/", $paragraph) ?: [$paragraph];
            $nameLineIndex = $signerName !== ''
                ? $this->findSignerLineIndex($lines, $signerName)
                : -1;

            if (count($lines) > 1 && $nameLineIndex >= 0) {
                foreach ($lines as $index => $line) {
                    $line = trim((string) $line);
                    if ($line === '') {
                        continue;
                    }

                    foreach ($this->renderLines($line, self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                        $operations[] = $lineOp;
                    }

                    if ($index === $nameLineIndex) {
                        $y = $this->appendSignature($pdf, $operations, $letter->signatureFile, $y);
                        $signatureInserted = $signatureInserted || $letter->signatureFile instanceof FileAsset;
                    }
                }

                continue;
            }

            if ($signerName !== '' && $this->matchesSigner($paragraph, $signerName)) {
                foreach ($this->renderLines($paragraph, self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                    $operations[] = $lineOp;
                }
                $y = $this->appendSignature($pdf, $operations, $letter->signatureFile, $y);
                $signatureInserted = $signatureInserted || $letter->signatureFile instanceof FileAsset;

                continue;
            }

            if ($signerTitle !== '' && $this->matchesSigner($paragraph, $signerTitle)) {
                foreach ($this->renderLines($paragraph, self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                    $operations[] = $lineOp;
                }

                continue;
            }

            foreach ($this->renderLines($paragraph, self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                $operations[] = $lineOp;
            }
        }

        if (! $signatureInserted && $signerName !== '') {
            $y -= 8;
            foreach ($this->renderLines('Yours faithfully,', self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                $operations[] = $lineOp;
            }
            foreach ($this->renderLines($signerName, self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                $operations[] = $lineOp;
            }
            $y = $this->appendSignature($pdf, $operations, $letter->signatureFile, $y);
            if ($signerTitle !== '') {
                foreach ($this->renderLines($signerTitle, self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                    $operations[] = $lineOp;
                }
            }
        }

        $pdf->addPage($operations);

        return $pdf->build();
    }

    /** @return array<int, string> */
    private function letterheadOperations(SimplePdfDocument $pdf, ?FileAsset $letterhead): array
    {
        if (! $letterhead instanceof FileAsset) {
            return [];
        }

        $jpeg = $this->jpegBytes($letterhead);
        if ($jpeg === null) {
            return [];
        }

        $info = getimagesizefromstring($jpeg);
        if (! is_array($info)) {
            return [];
        }

        $pdf->addJpegImage('Letterhead', $jpeg, (int) $info[0], (int) $info[1]);

        return [
            'q',
            self::PAGE_WIDTH.' 0 0 '.self::PAGE_HEIGHT.' 0 0 cm',
            '/Letterhead Do',
            'Q',
        ];
    }

    /** @return array<int, string> */
    private function simplePreamble(string $issuedOn, string $reference, string $applicant): array
    {
        return [
            'BT /F1 10 Tf',
            '72 '.self::CONTENT_TOP_Y.' Td (Date: '.SimplePdfDocument::escapeText($issuedOn).') Tj',
            '0 -16 Td (Ref: '.SimplePdfDocument::escapeText($reference).') Tj',
            '0 -24 Td /F1 11 Tf (Dear '.SimplePdfDocument::escapeText($applicant).',) Tj',
            'ET',
        ];
    }

    /** @return array<int, string> */
    private function renderLines(string $text, int $fontSize, int $lineHeight, int &$y): array
    {
        $operations = [];
        foreach ($this->wrapText($text, 82) as $line) {
            if ($y < self::CONTENT_BOTTOM_Y) {
                break;
            }
            $operations[] = 'BT /F1 '.$fontSize.' Tf '.self::MARGIN_X.' '.$y.' Td ('.SimplePdfDocument::escapeText($line).') Tj ET';
            $y -= $lineHeight;
        }

        return $operations;
    }

    /** @param  array<int, string>  $operations */
    private function appendSignature(
        SimplePdfDocument $pdf,
        array &$operations,
        ?FileAsset $signatureFile,
        int $y,
    ): int {
        if (! $signatureFile instanceof FileAsset) {
            return $y - 6;
        }

        $signatureJpeg = $this->jpegBytes($signatureFile);
        if ($signatureJpeg === null) {
            return $y - 6;
        }

        $info = getimagesizefromstring($signatureJpeg);
        if (! is_array($info)) {
            return $y - 6;
        }

        $imageName = $this->nextSignatureImageName();
        $pdf->addJpegImage($imageName, $signatureJpeg, (int) $info[0], (int) $info[1]);
        $drawWidth = 140;
        $drawHeight = 48;
        $signatureY = $y - $drawHeight;
        $operations[] = 'q';
        $operations[] = "{$drawWidth} 0 0 {$drawHeight} ".self::MARGIN_X." {$signatureY} cm";
        $operations[] = "/{$imageName} Do";
        $operations[] = 'Q';

        return $signatureY - 10;
    }

    private function nextSignatureImageName(): string
    {
        $this->signatureImageCounter++;

        return 'Signature'.$this->signatureImageCounter;
    }

    private function isStructuredTemplate(string $body): bool
    {
        return strlen($body) > 400
            || str_contains($body, 'ADMISSION & ACCEPTANCE LETTER')
            || str_contains($body, 'YOUR KCA COMMITMENT')
            || str_contains($body, 'YOUR COMMITMENT')
            || str_contains($body, '12-SESSION JOURNEY');
    }

    private function shouldSkipOverlayPreambleLine(string $paragraph): bool
    {
        $normalized = trim($paragraph);

        if (preg_match('/^Ref\.?\s*No\.?\s*:/i', $normalized) === 1) {
            return true;
        }

        if (preg_match('/^Date\s*:/i', $normalized) === 1) {
            return true;
        }

        return preg_match('/^Dear\s+/i', $normalized) === 1;
    }

    private function shouldStopRendering(string $paragraph): bool
    {
        $normalized = strtoupper(trim($paragraph));

        return str_starts_with($normalized, "APPLICANT'S ACCEPTANCE")
            || str_starts_with($normalized, 'APPLICANT ACCEPTANCE')
            || str_starts_with($normalized, 'PARENT/GUARDIAN CONFIRMATION');
    }

    private function shouldSkipLetterheadBannerBlock(string $paragraph): bool
    {
        $normalized = strtoupper(trim($paragraph));

        return in_array($normalized, [
            'THE FAMILY HOUSE OF GOD INTERNATIONAL',
            'KINGDOM CHANGE AGENTS (KCA)',
            'YOUTH DISCIPLESHIP TRAINING PROGRAMME',
            'ADMISSION & ACCEPTANCE LETTER',
        ], true);
    }

    private function isSectionHeading(string $paragraph): bool
    {
        $trimmed = trim($paragraph);

        return strlen($trimmed) >= 8
            && $trimmed === strtoupper($trimmed)
            && ! str_contains($trimmed, ':')
            && preg_match('/^[A-Z0-9 \'&().-]+$/', $trimmed) === 1;
    }

    /** @param  array<int, string>  $lines */
    private function findSignerLineIndex(array $lines, string $signerName): int
    {
        foreach ($lines as $index => $line) {
            if ($this->matchesSigner((string) $line, $signerName)) {
                return $index;
            }
        }

        return -1;
    }

    private function matchesSigner(string $value, string $expected): bool
    {
        $left = strtolower(trim($value));
        $right = strtolower(trim($expected));
        if ($left === '' || $right === '') {
            return false;
        }

        return $left === $right
            || str_starts_with($left, $right.',')
            || str_starts_with($right, $left.',');
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

        if (function_exists('imagesavealpha')) {
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            imagedestroy($image);

            return null;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        ob_start();
        imagejpeg($canvas, null, 92);
        imagedestroy($canvas);
        $jpeg = ob_get_clean();

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }
}
