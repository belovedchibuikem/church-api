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

    private const MARGIN_RIGHT = 43;

    private const CONTENT_WIDTH = 526;

    private const FOOTER_HEIGHT = 42;

    private const HEADER_LINE_Y = 662;

    private const HEADER_GOLD_LINE_Y = 657;

    private const LOGO_X = 43;

    private const LOGO_BOTTOM_Y = 668;

    private const LOGO_HEIGHT = 78;

    private const HEADER_TEXT_X = 134;

    private const CONTENT_TOP_Y = 632;

    private const CONTENT_BOTTOM_Y = 56;

    private const BODY_FONT_SIZE = 10;

    private const HEADING_FONT_SIZE = 10;

    private const LINE_HEIGHT = 13;

    private const HEADING_LINE_HEIGHT = 15;

    /** @var array{0: float, 1: float, 2: float} */
    private const COLOR_NAVY = [0.102, 0.243, 0.553];

    /** @var array{0: float, 1: float, 2: float} */
    private const COLOR_GOLD = [0.722, 0.580, 0.310];

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
        $operations = $this->brandedFooter();

        $logoJpeg = $this->resolveLogoJpeg($letter->letterheadFile);
        $operations = array_merge($operations, $this->brandedHeader($pdf, $logoJpeg));

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

        $structured = $this->isStructuredTemplate($body);
        $signerName = trim((string) ($letter->signer_name ?? ''));
        $signerTitle = trim((string) ($letter->signer_title ?? ''));
        $signatureInserted = false;
        $signerTitleRendered = false;
        $y = self::CONTENT_TOP_Y;

        $applicant = PersonDisplayName::of($letter->application?->person) ?: 'Applicant';
        $reference = $referenceCode !== '' ? $referenceCode : 'Pending';
        $issuedOn = $letter->issued_at?->format('d/m/Y') ?? now()->format('d/m/Y');
        $operations = array_merge($operations, $this->letterMeta($issuedOn, $reference, $applicant, $y));
        $y -= 4;

        foreach (preg_split("/\R\R+/", $body) ?: [] as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '' || $this->shouldStopRendering($paragraph)) {
                break;
            }

            if ($this->shouldSkipLetterheadBannerBlock($paragraph)) {
                continue;
            }

            if ($structured && $this->shouldSkipOverlayPreambleLine($paragraph)) {
                continue;
            }

            if ($this->isSectionHeading($paragraph)) {
                $y -= 6;
                foreach ($this->renderLines($paragraph, 'F2', self::HEADING_FONT_SIZE, self::HEADING_LINE_HEIGHT, $y, self::COLOR_NAVY) as $lineOp) {
                    $operations[] = $lineOp;
                }
                $y -= 2;

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

                    if ($index === $nameLineIndex) {
                        foreach ($this->renderLines($line, 'F3', 12, 15, $y) as $lineOp) {
                            $operations[] = $lineOp;
                        }
                        $y = $this->appendSignature($pdf, $operations, $letter->signatureFile, $y, $signerTitle);
                        $signatureInserted = true;
                        $signerTitleRendered = $signerTitle !== '';

                        continue;
                    }

                    if ($signerTitle !== '' && $this->matchesSigner($line, $signerTitle)) {
                        if (! $signerTitleRendered) {
                            foreach ($this->renderLines($line, 'F2', self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                                $operations[] = $lineOp;
                            }
                            $signerTitleRendered = true;
                        }

                        continue;
                    }

                    foreach ($this->renderLines($line, 'F1', self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                        $operations[] = $lineOp;
                    }
                }

                continue;
            }

            if ($signerName !== '' && $this->matchesSigner($paragraph, $signerName)) {
                foreach ($this->renderLines($paragraph, 'F3', 12, 15, $y) as $lineOp) {
                    $operations[] = $lineOp;
                }
                $y = $this->appendSignature($pdf, $operations, $letter->signatureFile, $y, $signerTitle);
                $signatureInserted = true;
                $signerTitleRendered = $signerTitle !== '';

                continue;
            }

            if ($signerTitle !== '' && $this->matchesSigner($paragraph, $signerTitle)) {
                if (! $signerTitleRendered) {
                    foreach ($this->renderLines($paragraph, 'F2', self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                        $operations[] = $lineOp;
                    }
                    $signerTitleRendered = true;
                }

                continue;
            }

            foreach ($this->renderLines($paragraph, 'F1', self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                $operations[] = $lineOp;
            }
        }

        if (! $signatureInserted && $signerName !== '') {
            $y -= 8;
            foreach ($this->renderLines('Yours faithfully,', 'F1', self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                $operations[] = $lineOp;
            }
            foreach ($this->renderLines($signerName, 'F3', 12, 15, $y) as $lineOp) {
                $operations[] = $lineOp;
            }
            $y = $this->appendSignature($pdf, $operations, $letter->signatureFile, $y, $signerTitle);
        }

        $pdf->addPage($operations);

        return $pdf->build();
    }

    /** @return array<int, string> */
    private function brandedHeader(SimplePdfDocument $pdf, ?string $logoJpeg): array
    {
        $operations = [];

        if ($logoJpeg !== null) {
            $info = getimagesizefromstring($logoJpeg);
            if (is_array($info)) {
                $pdf->addJpegImage('Logo', $logoJpeg, (int) $info[0], (int) $info[1]);
                $aspect = (int) $info[0] / max(1, (int) $info[1]);
                $drawWidth = self::LOGO_HEIGHT * $aspect;
                $operations[] = 'q';
                $operations[] = sprintf(
                    '%.2f 0 0 %.2f %.2f %.2f cm',
                    $drawWidth,
                    self::LOGO_HEIGHT,
                    self::LOGO_X,
                    self::LOGO_BOTTOM_Y,
                );
                $operations[] = '/Logo Do';
                $operations[] = 'Q';
            }
        }

        $operations = array_merge($operations, $this->coloredText(
            'KINGDOM CHANGE AGENTS (KCA)',
            self::HEADER_TEXT_X,
            728,
            'F2',
            13,
            self::COLOR_NAVY,
        ));
        $operations = array_merge($operations, $this->coloredText(
            "THE FAMILY HOUSE OF GOD INT'L",
            self::HEADER_TEXT_X,
            710,
            'F2',
            11,
            self::COLOR_NAVY,
        ));
        $operations = array_merge($operations, $this->coloredText(
            'Equipping Kingdom Leaders • Transforming Nations',
            self::HEADER_TEXT_X,
            694,
            'F3',
            9,
            self::COLOR_GOLD,
        ));

        $operations = array_merge($operations, $this->horizontalLine(self::HEADER_LINE_Y, 2.0, self::COLOR_NAVY));
        $operations = array_merge($operations, $this->horizontalLine(self::HEADER_GOLD_LINE_Y, 0.75, self::COLOR_GOLD));

        return $operations;
    }

    /** @return array<int, string> */
    private function brandedFooter(): array
    {
        [$r, $g, $b] = self::COLOR_NAVY;
        $leftLabel = 'Kingdom Change Agents';
        $rightLabel = 'Admission Office';
        $labelWidth = 220.0;
        $startX = (self::PAGE_WIDTH - $labelWidth) / 2;
        $bulletX = $startX + 118;
        $operations = [
            'q',
            sprintf('%.3f %.3f %.3f rg', $r, $g, $b),
            '0 0 '.self::PAGE_WIDTH.' '.self::FOOTER_HEIGHT.' re f',
            'Q',
            ...$this->coloredText($leftLabel, $startX, 16, 'F2', 9, [1, 1, 1]),
            ...$this->coloredText($rightLabel, $bulletX + 10, 16, 'F2', 9, [1, 1, 1]),
        ];

        [$gr, $gg, $gb] = self::COLOR_GOLD;
        $operations[] = 'q';
        $operations[] = sprintf('%.3f %.3f %.3f rg', $gr, $gg, $gb);
        $operations[] = sprintf('%.2f 14 5 5 re f', $bulletX - 1);
        $operations[] = 'Q';

        return $operations;
    }

    /** @return array<int, string> */
    private function letterMeta(string $issuedOn, string $reference, string $applicant, int &$y): array
    {
        $operations = [
            ...$this->plainText('Ref. No.: '.$reference, self::MARGIN_X, $y, 'F1', 10),
            ...$this->plainText('Date: '.$issuedOn, 468, $y, 'F1', 10),
        ];
        $y -= 18;
        $operations = array_merge($operations, $this->plainText(
            'Dear '.$applicant.',',
            self::MARGIN_X,
            $y,
            'F1',
            11,
        ));
        $y -= 20;

        return $operations;
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $rgb
     * @return array<int, string>
     */
    private function coloredText(
        string $text,
        float $x,
        float $y,
        string $font,
        int $size,
        array $rgb,
    ): array {
        [$r, $g, $b] = $rgb;

        return [
            'q',
            sprintf('%.3f %.3f %.3f rg', $r, $g, $b),
            "BT /{$font} {$size} Tf {$x} {$y} Td (".SimplePdfDocument::escapeText($text).') Tj ET',
            'Q',
        ];
    }

    /** @return array<int, string> */
    private function plainText(string $text, float $x, float $y, string $font, int $size): array
    {
        return [
            "BT /{$font} {$size} Tf {$x} {$y} Td (".SimplePdfDocument::escapeText($text).') Tj ET',
        ];
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $rgb
     * @return array<int, string>
     */
    private function horizontalLine(float $y, float $width, array $rgb): array
    {
        [$r, $g, $b] = $rgb;
        $x2 = self::PAGE_WIDTH - self::MARGIN_RIGHT;

        return [
            'q',
            sprintf('%.3f %.3f %.3f RG', $r, $g, $b),
            sprintf('%.2f w', $width),
            sprintf('%.2f %.2f m', self::MARGIN_X, $y),
            sprintf('%.2f %.2f l S', $x2, $y),
            'Q',
        ];
    }

    /** @param  array<int, string>  $operations */
    private function appendSignature(
        SimplePdfDocument $pdf,
        array &$operations,
        ?FileAsset $signatureFile,
        int $y,
        ?string $signerTitle = null,
    ): int {
        $lineY = $y - 4;

        if ($signatureFile instanceof FileAsset) {
            $signatureJpeg = $this->jpegBytes($signatureFile);
            if ($signatureJpeg !== null) {
                $info = getimagesizefromstring($signatureJpeg);
                if (is_array($info)) {
                    $imageName = $this->nextSignatureImageName();
                    $pdf->addJpegImage($imageName, $signatureJpeg, (int) $info[0], (int) $info[1]);
                    $drawWidth = 150;
                    $drawHeight = 52;
                    $signatureY = $y - $drawHeight;
                    $operations[] = 'q';
                    $operations[] = "{$drawWidth} 0 0 {$drawHeight} ".self::MARGIN_X." {$signatureY} cm";
                    $operations[] = "/{$imageName} Do";
                    $operations[] = 'Q';
                    $lineY = $signatureY - 8;
                }
            }
        }

        $lineEnd = self::MARGIN_X + 150;
        $operations[] = 'q 0 0 0 RG 0.75 w';
        $operations[] = self::MARGIN_X.' '.$lineY.' m '.$lineEnd.' '.$lineY.' l S Q';
        $y = $lineY - 14;

        if ($signerTitle !== null && trim($signerTitle) !== '') {
            foreach ($this->renderLines($signerTitle, 'F2', self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                $operations[] = $lineOp;
            }
        }

        return $y - 4;
    }

    private function resolveLogoJpeg(?FileAsset $letterhead): ?string
    {
        if ($letterhead instanceof FileAsset) {
            $jpeg = $this->jpegBytes($letterhead);
            if ($jpeg !== null) {
                return $jpeg;
            }
        }

        $path = resource_path('kca/admission-letter-logo.jpg');
        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $bytes = file_get_contents($path);

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
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
            'THE FAMILY HOUSE OF GOD INT\'L',
            "THE FAMILY HOUSE OF GOD INT'L",
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

    /**
     * @param  array{0: float, 1: float, 2: float}|null  $rgb
     * @return array<int, string>
     */
    private function renderLines(
        string $text,
        string $font,
        int $fontSize,
        int $lineHeight,
        int &$y,
        ?array $rgb = null,
    ): array {
        $operations = [];
        foreach ($this->wrapText($text, 82) as $line) {
            if ($y < self::CONTENT_BOTTOM_Y) {
                break;
            }

            if ($rgb !== null) {
                $operations = array_merge($operations, $this->coloredText($line, self::MARGIN_X, $y, $font, $fontSize, $rgb));
            } else {
                $operations[] = "BT /{$font} {$fontSize} Tf ".self::MARGIN_X.' '.$y.' Td ('.SimplePdfDocument::escapeText($line).') Tj ET';
            }
            $y -= $lineHeight;
        }

        return $operations;
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

    private function nextSignatureImageName(): string
    {
        $this->signatureImageCounter++;

        return 'Signature'.$this->signatureImageCounter;
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
