<?php

namespace App\Support\Kca;

use App\Files\FileAssetStatus;
use App\Files\Queries\OpenFileAssetStreamQuery;
use App\Models\FileAsset;
use App\Models\KcaAdmissionLetter;
use App\Storage\Contracts\ObjectStorageDiskResolver;
use App\Support\Identity\PersonDisplayName;
use App\Support\Pdf\SimplePdfDocument;

class KcaAdmissionLetterPdfRenderer
{
    private const PAGE_WIDTH = 612;

    private const PAGE_HEIGHT = 792;

    /** Balanced content margins (~0.85"). */
    private const MARGIN_X = 61;

    private const CONTENT_WIDTH = 490;

    /** Clear the branded zone on the full-page letterhead template. */
    private const CONTENT_TOP_Y = 450;

    private const CONTENT_BOTTOM_Y = 58;

    private const BODY_FONT_SIZE = 10;

    private const HEADING_FONT_SIZE = 10;

    private const LINE_HEIGHT = 14;

    private const LIST_LINE_HEIGHT = 13;

    private const HEADING_LINE_HEIGHT = 15;

    private const BULLET_INDENT = 14;

    /** @var array{0: float, 1: float, 2: float} */
    private const COLOR_NAVY = [0.102, 0.243, 0.553];

    /**
     * Adobe Times-Roman glyph widths (1/1000 em) for printable ASCII.
     *
     * @var array<int, int>
     */
    private const TIMES_WIDTHS = [
        32 => 250, 33 => 333, 34 => 408, 35 => 500, 36 => 500, 37 => 833, 38 => 778, 39 => 180,
        40 => 333, 41 => 333, 42 => 500, 43 => 564, 44 => 250, 45 => 333, 46 => 250, 47 => 278,
        48 => 500, 49 => 500, 50 => 500, 51 => 500, 52 => 500, 53 => 500, 54 => 500, 55 => 500,
        56 => 500, 57 => 500, 58 => 278, 59 => 278, 60 => 564, 61 => 564, 62 => 564, 63 => 444,
        64 => 921, 65 => 722, 66 => 667, 67 => 667, 68 => 722, 69 => 611, 70 => 556, 71 => 722,
        72 => 722, 73 => 333, 74 => 389, 75 => 722, 76 => 611, 77 => 889, 78 => 722, 79 => 722,
        80 => 556, 81 => 722, 82 => 667, 83 => 556, 84 => 611, 85 => 722, 86 => 722, 87 => 944,
        88 => 722, 89 => 722, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 469, 95 => 500,
        96 => 333, 97 => 444, 98 => 500, 99 => 444, 100 => 500, 101 => 444, 102 => 333, 103 => 500,
        104 => 500, 105 => 278, 106 => 278, 107 => 500, 108 => 278, 109 => 778, 110 => 500, 111 => 500,
        112 => 500, 113 => 500, 114 => 333, 115 => 389, 116 => 278, 117 => 500, 118 => 500, 119 => 722,
        120 => 500, 121 => 500, 122 => 444, 123 => 480, 124 => 200, 125 => 480, 126 => 541,
    ];

    /** @var list<string> */
    private const JOURNEY_SESSIONS = [
        'The Call of the King',
        'Born into the Kingdom',
        'Living as a Child of the King',
        'Walking with the Holy Spirit',
        "At the King's Feet",
        'Becoming Like Jesus',
        'Every Disciple Is a Servant',
        "The Church: God's Family on Mission",
        'Holiness in a Compromised World',
        'Sharing the Gospel',
        'Kingdom Influence',
        'Becoming a Kingdom Change Agent',
    ];

    /** @var list<string> */
    private const COMMITMENT_ITEM_STARTS = [
        'Attend at least',
        'Actively participate',
        'Complete all',
        'Serve in at least',
        'Engage with',
        'Uphold Christian',
    ];

    private int $signatureImageCounter = 0;

    public function __construct(
        private readonly OpenFileAssetStreamQuery $fileAssetStreams,
        private readonly ObjectStorageDiskResolver $storageResolver,
    ) {}

    public function render(KcaAdmissionLetter $letter): string
    {
        $letter->loadMissing([
            'application.person.profile',
        ]);

        // Always reload full file rows — controllers often eager-load only id/public_id,
        // which strips storage fields needed to open template/signature bytes.
        $letterhead = $this->hydrateImageAsset($letter->letterhead_file_asset_id);
        $signature = $this->hydrateImageAsset($letter->signature_file_asset_id);
        $letter->setRelation('letterheadFile', $letterhead);
        $letter->setRelation('signatureFile', $signature);

        $pdf = new SimplePdfDocument;
        $this->signatureImageCounter = 0;
        $operations = $this->pageTemplateBackground($pdf, $this->resolveTemplateJpeg($letterhead));

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
        $body = $this->normalizeLetterBody($body);

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
        $y -= 10;

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
                $y -= 8;
                foreach ($this->renderLines($paragraph, 'F2', self::HEADING_FONT_SIZE, self::HEADING_LINE_HEIGHT, $y, self::COLOR_NAVY) as $lineOp) {
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

                    if ($index === $nameLineIndex) {
                        foreach ($this->renderLines($line, 'F3', 11, 13, $y) as $lineOp) {
                            $operations[] = $lineOp;
                        }
                        $y = $this->appendSignature($pdf, $operations, $signature, $y, $signerTitle);
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

                    foreach ($this->renderContentLines($line, $y) as $lineOp) {
                        $operations[] = $lineOp;
                    }
                }

                continue;
            }

            if ($signerName !== '' && $this->matchesSigner($paragraph, $signerName)) {
                foreach ($this->renderLines($paragraph, 'F3', 11, 13, $y) as $lineOp) {
                    $operations[] = $lineOp;
                }
                $y = $this->appendSignature($pdf, $operations, $signature, $y, $signerTitle);
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

            if ($this->isJourneyListParagraph($paragraph)) {
                $y = $this->renderJourneyColumns($paragraph, $operations, $y);

                continue;
            }

            if ($this->isBulletListParagraph($paragraph)) {
                $y = $this->renderBulletList($paragraph, $operations, $y);

                continue;
            }

            foreach ($this->renderContentLines($paragraph, $y) as $lineOp) {
                $operations[] = $lineOp;
            }
            $y -= 4;
        }

        if (! $signatureInserted) {
            $y -= 6;
            foreach ($this->renderLines('Yours faithfully,', 'F1', self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                $operations[] = $lineOp;
            }
            if ($signerName !== '') {
                foreach ($this->renderLines($signerName, 'F3', 11, 13, $y) as $lineOp) {
                    $operations[] = $lineOp;
                }
            }
            $y = $this->appendSignature($pdf, $operations, $signature, $y, $signerTitleRendered ? null : $signerTitle);
        }

        $pdf->addPage($operations);

        return $pdf->build();
    }

    /**
     * Stretch the letterhead image across the full letter page as the visual template.
     * Letter content is drawn on top; no separate title, logo, or footer chrome is added.
     *
     * @return array<int, string>
     */
    private function pageTemplateBackground(SimplePdfDocument $pdf, ?string $templateJpeg): array
    {
        if ($templateJpeg === null) {
            return [];
        }

        $info = getimagesizefromstring($templateJpeg);
        if (! is_array($info) || (int) $info[0] < 1 || (int) $info[1] < 1) {
            return [];
        }

        $pdf->addJpegImage('Letterhead', $templateJpeg, (int) $info[0], (int) $info[1]);

        return [
            'q',
            sprintf('%.2f 0 0 %.2f 0 0 cm', self::PAGE_WIDTH, self::PAGE_HEIGHT),
            '/Letterhead Do',
            'Q',
        ];
    }

    /** @return array<int, string> */
    private function letterMeta(string $issuedOn, string $reference, string $applicant, int &$y): array
    {
        $dateLabel = 'Date: '.$issuedOn;
        $dateX = self::PAGE_WIDTH - self::MARGIN_X - $this->stringWidth($dateLabel, 10);
        $operations = [
            ...$this->plainText('Ref. No.: '.$reference, self::MARGIN_X, $y, 'F1', 10),
            ...$this->plainText($dateLabel, max(self::MARGIN_X, $dateX), $y, 'F1', 10),
        ];
        $y -= 22;
        $operations = array_merge($operations, $this->plainText(
            'Dear '.$applicant.',',
            self::MARGIN_X,
            $y,
            'F1',
            11,
        ));
        $y -= 22;

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

    /** @param  array<int, string>  $operations */
    private function appendSignature(
        SimplePdfDocument $pdf,
        array &$operations,
        ?FileAsset $signatureFile,
        int $y,
        ?string $signerTitle = null,
    ): int {
        // Keep the signature block tight under the printed name.
        $gapAfterName = 3;
        $lineY = $y - $gapAfterName - 10;
        $drewSignature = false;

        if ($signatureFile instanceof FileAsset) {
            $signatureJpeg = $this->signatureJpegBytes($signatureFile);
            if ($signatureJpeg !== null) {
                $info = getimagesizefromstring($signatureJpeg);
                if (is_array($info) && (int) $info[0] > 0 && (int) $info[1] > 0) {
                    $imageName = $this->nextSignatureImageName();
                    $pdf->addJpegImage($imageName, $signatureJpeg, (int) $info[0], (int) $info[1]);

                    $drawWidth = 150.0;
                    $aspect = (int) $info[0] / max(1, (int) $info[1]);
                    $drawHeight = min(28.0, max(16.0, $drawWidth / max(0.01, $aspect)));

                    // Place the signature so its baseline sits on the underline.
                    $lineY = $y - $gapAfterName - (int) round($drawHeight);
                    $signatureY = $lineY;

                    $operations[] = 'q';
                    $operations[] = sprintf(
                        '%.2f 0 0 %.2f %.2f %.2f cm',
                        $drawWidth,
                        $drawHeight,
                        self::MARGIN_X,
                        $signatureY,
                    );
                    $operations[] = "/{$imageName} Do";
                    $operations[] = 'Q';
                    $drewSignature = true;
                }
            }
        }

        $lineEnd = self::MARGIN_X + ($drewSignature ? 150 : 160);
        $operations[] = 'q 0 0 0 RG 0.8 w';
        $operations[] = sprintf('%.2f %.2f m %.2f %.2f l S Q', self::MARGIN_X, $lineY, $lineEnd, $lineY);
        $y = $lineY - 12;

        if ($signerTitle !== null && trim($signerTitle) !== '') {
            foreach ($this->renderLines($signerTitle, 'F2', self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y) as $lineOp) {
                $operations[] = $lineOp;
            }
        }

        return $y - 2;
    }

    private function signatureJpegBytes(FileAsset $asset): ?string
    {
        $bytes = $this->readAssetBytes($asset);
        if ($bytes === null) {
            return null;
        }

        return $this->prepareSignatureJpeg($bytes) ?? $this->normalizeToJpeg($bytes);
    }

    /**
     * Flatten, trim whitespace, and remove an embedded underline so the PDF can
     * draw one clean signature line under the ink.
     */
    private function prepareSignatureJpeg(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return $this->normalizeToJpeg($bytes);
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1) {
            imagedestroy($source);

            return null;
        }

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            imagedestroy($source);

            return null;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        $this->eraseSignatureUnderline($canvas);
        $cropped = $this->cropSignatureInk($canvas);
        if ($cropped !== $canvas) {
            imagedestroy($canvas);
            $canvas = $cropped;
        }

        if ($canvas === false) {
            return null;
        }

        ob_start();
        imagejpeg($canvas, null, 92);
        imagedestroy($canvas);
        $jpeg = ob_get_clean();

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }

    /** @param  \GdImage  $image */
    private function eraseSignatureUnderline($image): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 20 || $height < 12) {
            return;
        }

        $scanFrom = (int) floor($height * 0.55);
        $minDarkSpan = (int) max(24, floor($width * 0.35));
        $white = imagecolorallocate($image, 255, 255, 255);

        for ($y = $scanFrom; $y < $height; $y++) {
            $darkRuns = 0;
            $maxRun = 0;
            $run = 0;
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $isDark = (($r + $g + $b) / 3) < 140;
                if ($isDark) {
                    $run++;
                    $maxRun = max($maxRun, $run);
                    $darkRuns++;
                } else {
                    $run = 0;
                }
            }

            $isLine = $maxRun >= $minDarkSpan && $darkRuns >= (int) ($width * 0.28);
            if (! $isLine) {
                continue;
            }

            // Clear this row and a thin band around it so the embedded rule disappears.
            for ($yy = max($scanFrom, $y - 1); $yy <= min($height - 1, $y + 1); $yy++) {
                imageline($image, 0, $yy, $width - 1, $yy, $white);
            }
        }
    }

    /**
     * @param  \GdImage  $image
     * @return \GdImage|false
     */
    private function cropSignatureInk($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ((($r + $g + $b) / 3) > 245) {
                    continue;
                }
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            return $image;
        }

        $pad = 2;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $maxX = min($width - 1, $maxX + $pad);
        $maxY = min($height - 1, $maxY + $pad);
        $cropW = $maxX - $minX + 1;
        $cropH = $maxY - $minY + 1;

        if ($cropW >= $width - 4 && $cropH >= $height - 4) {
            return $image;
        }

        $cropped = imagecrop($image, [
            'x' => $minX,
            'y' => $minY,
            'width' => $cropW,
            'height' => $cropH,
        ]);

        return $cropped === false ? $image : $cropped;
    }

    private function resolveTemplateJpeg(?FileAsset $letterhead): ?string
    {
        if ($letterhead instanceof FileAsset) {
            $jpeg = $this->jpegBytes($letterhead);
            if ($jpeg !== null) {
                return $jpeg;
            }
        }

        foreach ([
            resource_path('kca/admission-letter-template.jpg'),
            resource_path('kca/admission-letter-logo.jpg'),
        ] as $path) {
            if (! is_string($path) || ! is_file($path)) {
                continue;
            }

            $bytes = file_get_contents($path);
            if (! is_string($bytes) || $bytes === '') {
                continue;
            }

            return $this->normalizeToJpeg($bytes) ?? $bytes;
        }

        return null;
    }

    private function hydrateImageAsset(int|string|null $fileAssetId): ?FileAsset
    {
        if ($fileAssetId === null || $fileAssetId === '') {
            return null;
        }

        $asset = FileAsset::query()
            ->whereKey($fileAssetId)
            ->whereNull('deleted_at')
            ->first();

        if ($asset === null) {
            return null;
        }

        // Governance uploads can remain Pending until first preview stream.
        // PDF download must still embed them, so promote Pending/Quarantined assets.
        if (in_array($asset->status, [FileAssetStatus::Pending, FileAssetStatus::Quarantined], true)) {
            $asset->forceFill([
                'status' => FileAssetStatus::Available,
                'available_at' => $asset->available_at ?? now()->utc(),
            ])->save();
        }

        return $asset->status === FileAssetStatus::Available ? $asset->refresh() : null;
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
        $trimmed = rtrim(trim($paragraph), ':');

        return strlen($trimmed) >= 8
            && $trimmed === strtoupper($trimmed)
            && ! str_contains($trimmed, "\n")
            && preg_match('/^[A-Z0-9 \'&().-]+$/', $trimmed) === 1;
    }

    /**
     * Expand condensed "HEADING: run-on text" blocks and keep list items on their own lines.
     */
    private function normalizeLetterBody(string $body): string
    {
        $body = $this->polishLetterCopy($body);
        $body = preg_replace(
            '/\R*(?=(?:YOUR(?:\s+KCA)?\s+COMMITMENT|12-SESSION\s+JOURNEY|YOUR\s+DISCIPLESHIP\s+JOURNEY|(?:YOUR\s+KCA\s+)?DECLARATION)\s*:)/i',
            "\n\n",
            $body,
        ) ?? $body;

        $parts = [];

        foreach (preg_split("/\R\R+/", $body) ?: [] as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }

            $expanded = $this->expandCondensedSection($paragraph);
            if ($expanded !== null) {
                $parts[] = $expanded;

                continue;
            }

            if ($this->looksLikeJourneyRunOn($paragraph)) {
                $parts[] = "12-SESSION JOURNEY\n\n".$this->formatJourneyLines($paragraph);

                continue;
            }

            if ($this->looksLikeCommitmentRunOn($paragraph)) {
                $parts[] = "YOUR COMMITMENT\n\n".$this->formatCommitmentLines($paragraph);

                continue;
            }

            $parts[] = $paragraph;
        }

        return implode("\n\n", $parts);
    }

    private function polishLetterCopy(string $body): string
    {
        $replacements = [
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{2013}" => '-',
            "\u{2014}" => '—',
            "\u{00A0}" => ' ',
        ];
        $body = str_replace(array_keys($replacements), array_values($replacements), $body);
        $body = preg_replace("/[ \t]+/u", ' ', $body) ?? $body;
        $body = preg_replace('/\bInternational\s+a journey\b/i', 'International, a journey', $body) ?? $body;
        $body = preg_replace('/\bbeginning\s+success\b/i', 'beginning. Success', $body) ?? $body;
        $body = preg_replace(
            '/\bUphold Christian conduct\s+respect,\s*humility,\s*integrity,\s*discipline,\s*love\b/i',
            'Uphold Christian conduct: respect, humility, integrity, discipline, and love',
            $body,
        ) ?? $body;

        return trim($body);
    }

    private function expandCondensedSection(string $paragraph): ?string
    {
        if (preg_match('/^(YOUR\s+KCA\s+COMMITMENT|YOUR\s+COMMITMENT)\s*:\s*(.+)$/is', $paragraph, $matches) === 1) {
            return "YOUR COMMITMENT\n\n".$this->formatCommitmentLines((string) $matches[2]);
        }

        if (preg_match('/^(YOUR\s+DISCIPLESHIP\s+JOURNEY|12-SESSION\s+JOURNEY)\s*:\s*(.+)$/is', $paragraph, $matches) === 1) {
            return "12-SESSION JOURNEY\n\n".$this->formatJourneyLines((string) $matches[2]);
        }

        if (preg_match('/^(YOUR\s+KCA\s+DECLARATION|DECLARATION)\s*:\s*(.+)$/is', $paragraph, $matches) === 1) {
            $lines = preg_split('/(?<=[.!?])\s+|(?=\bI AM A KINGDOM CHANGE AGENT\b)/i', trim((string) $matches[2])) ?: [];
            $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));

            return "DECLARATION\n\n".implode("\n", $lines);
        }

        return null;
    }

    private function looksLikeCommitmentRunOn(string $paragraph): bool
    {
        $normalized = strtolower($paragraph);

        return ! str_contains($paragraph, "\n")
            && str_contains($normalized, 'attend at least')
            && str_contains($normalized, 'actively participate')
            && str_contains($normalized, 'complete all');
    }

    private function looksLikeJourneyRunOn(string $paragraph): bool
    {
        return ! str_contains($paragraph, "\n")
            && str_contains($paragraph, 'The Call of the King')
            && str_contains($paragraph, 'Becoming a Kingdom Change Agent');
    }

    private function formatCommitmentLines(string $text): string
    {
        $items = $this->splitByStarts($text, self::COMMITMENT_ITEM_STARTS);
        if ($items === []) {
            $items = [trim($text)];
        }

        return implode("\n", array_map(
            fn (string $item): string => '• '.$this->polishCommitmentItem($item),
            $items,
        ));
    }

    private function polishCommitmentItem(string $item): string
    {
        $item = trim($item);
        if (preg_match('/^Uphold Christian conduct\b/i', $item) === 1) {
            return 'Uphold Christian conduct: respect, humility, integrity, discipline, and love';
        }

        return $item;
    }

    private function formatJourneyLines(string $text): string
    {
        $items = $this->splitByStarts($text, self::JOURNEY_SESSIONS);
        if (count($items) < 4) {
            $fromLines = preg_split("/\R/", $text) ?: [];
            $fromLines = array_values(array_filter(array_map('trim', $fromLines), static fn (string $line): bool => $line !== ''));
            if (count($fromLines) >= 4) {
                $items = $fromLines;
            }
        }

        if ($items === []) {
            $items = [trim($text)];
        }

        return implode("\n", array_map(
            static fn (string $item): string => '• '.$item,
            $items,
        ));
    }

    /**
     * @param  list<string>  $starts
     * @return list<string>
     */
    private function splitByStarts(string $text, array $starts): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ($text === '') {
            return [];
        }

        $quoted = array_map(static fn (string $start): string => preg_quote($start, '/'), $starts);
        $pattern = '/(?='.implode('|', $quoted).')/i';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $part): string => trim($part, " \t\n\r\0\x0B•-"), $parts),
            static fn (string $part): bool => $part !== '',
        ));
    }

    private function isBulletListParagraph(string $paragraph): bool
    {
        $lines = preg_split("/\R/", $paragraph) ?: [];
        $bulletLines = 0;
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(?:•|-|\d+\.)\s+/u', $line) !== 1) {
                return false;
            }
            $bulletLines++;
        }

        return $bulletLines >= 2;
    }

    private function isJourneyListParagraph(string $paragraph): bool
    {
        if (! $this->isBulletListParagraph($paragraph)) {
            return false;
        }

        $plain = str_replace(['•', '-'], '', $paragraph);

        return str_contains($plain, 'The Call of the King')
            && str_contains($plain, 'Becoming a Kingdom Change Agent');
    }

    /** @param  array<int, string>  $operations */
    private function renderBulletList(string $paragraph, array &$operations, int $y): int
    {
        $textWidth = self::CONTENT_WIDTH - self::BULLET_INDENT;

        foreach (preg_split("/\R/", $paragraph) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $text = preg_replace('/^(?:•|-|\d+\.)\s+/u', '', $line) ?? $line;
            if ($y < self::CONTENT_BOTTOM_Y) {
                break;
            }

            $wrappedLines = $this->wrapTextToWidth($text, $textWidth, self::BODY_FONT_SIZE);
            foreach ($wrappedLines as $index => $wrapped) {
                if ($y < self::CONTENT_BOTTOM_Y) {
                    break 2;
                }
                if ($index === 0) {
                    $operations = array_merge($operations, $this->plainText('•', self::MARGIN_X, $y, 'F1', self::BODY_FONT_SIZE));
                }
                $operations = array_merge(
                    $operations,
                    $this->drawTextLine(
                        $wrapped,
                        self::MARGIN_X + self::BULLET_INDENT,
                        $y,
                        'F1',
                        self::BODY_FONT_SIZE,
                        justify: $index < count($wrappedLines) - 1,
                        maxWidth: $textWidth,
                    ),
                );
                $y -= self::LIST_LINE_HEIGHT;
            }
        }

        return $y - 4;
    }

    /** @param  array<int, string>  $operations */
    private function renderJourneyColumns(string $paragraph, array &$operations, int $y): int
    {
        $items = [];
        foreach (preg_split("/\R/", $paragraph) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $items[] = preg_replace('/^(?:•|-|\d+\.)\s+/u', '', $line) ?? $line;
        }

        $mid = (int) ceil(count($items) / 2);
        $left = array_slice($items, 0, $mid);
        $right = array_slice($items, $mid);
        $leftX = self::MARGIN_X;
        $columnGap = 18.0;
        $columnWidth = (self::CONTENT_WIDTH - $columnGap) / 2;
        $rightX = self::MARGIN_X + $columnWidth + $columnGap;
        $rows = max(count($left), count($right));

        for ($i = 0; $i < $rows; $i++) {
            if ($y < self::CONTENT_BOTTOM_Y) {
                break;
            }

            if (isset($left[$i])) {
                $operations = array_merge(
                    $operations,
                    $this->plainText('• '.$left[$i], $leftX, $y, 'F1', 9),
                );
            }
            if (isset($right[$i])) {
                $operations = array_merge(
                    $operations,
                    $this->plainText('• '.$right[$i], $rightX, $y, 'F1', 9),
                );
            }
            $y -= self::LIST_LINE_HEIGHT;
        }

        return $y - 4;
    }

    /**
     * @return array<int, string>
     */
    private function renderContentLines(string $text, int &$y): array
    {
        return $this->renderLines($text, 'F1', self::BODY_FONT_SIZE, self::LINE_HEIGHT, $y, justify: true);
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
        bool $justify = false,
    ): array {
        $operations = [];
        foreach (preg_split("/\R/", $text) ?: [$text] as $hardLine) {
            $hardLine = trim((string) $hardLine);
            if ($hardLine === '') {
                $y -= (int) max(4, $lineHeight / 2);

                continue;
            }

            $wrapped = $this->wrapTextToWidth($hardLine, self::CONTENT_WIDTH, $fontSize);
            $lastIndex = count($wrapped) - 1;
            foreach ($wrapped as $index => $line) {
                if ($y < self::CONTENT_BOTTOM_Y) {
                    break 2;
                }

                $operations = array_merge($operations, $this->drawTextLine(
                    $line,
                    self::MARGIN_X,
                    $y,
                    $font,
                    $fontSize,
                    justify: $justify && $index < $lastIndex,
                    maxWidth: self::CONTENT_WIDTH,
                    rgb: $rgb,
                ));
                $y -= $lineHeight;
            }
        }

        return $operations;
    }

    /**
     * @param  array{0: float, 1: float, 2: float}|null  $rgb
     * @return array<int, string>
     */
    private function drawTextLine(
        string $text,
        float $x,
        float $y,
        string $font,
        int $size,
        bool $justify = false,
        float $maxWidth = self::CONTENT_WIDTH,
        ?array $rgb = null,
    ): array {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));
        if ($words === []) {
            return [];
        }

        $line = implode(' ', $words);
        $tw = 0.0;
        if ($justify && count($words) > 1) {
            $wordsWidth = 0.0;
            foreach ($words as $word) {
                $wordsWidth += $this->stringWidth($word, $size);
            }
            $spaces = count($words) - 1;
            $extra = $maxWidth - $wordsWidth;
            if ($extra > 0.5) {
                $tw = $extra / $spaces;
            }
        }

        $ops = ['q'];
        if ($rgb !== null) {
            [$r, $g, $b] = $rgb;
            $ops[] = sprintf('%.3f %.3f %.3f rg', $r, $g, $b);
        }
        $ops[] = sprintf(
            'BT /%s %d Tf %.2f %.2f Td %.3f Tw (%s) Tj ET',
            $font,
            $size,
            $x,
            $y,
            $tw,
            SimplePdfDocument::escapeText($line),
        );
        $ops[] = 'Q';

        return $ops;
    }

    /** @return array<int, string> */
    private function wrapTextToWidth(string $text, float $maxWidth, int $fontSize): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));
        if ($words === []) {
            return [];
        }

        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if ($current !== '' && $this->stringWidth($candidate, $fontSize) > $maxWidth) {
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

    private function stringWidth(string $text, int $fontSize): float
    {
        $units = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $code = ord($text[$i]);
            $units += self::TIMES_WIDTHS[$code] ?? 500;
        }

        return $units * $fontSize / 1000.0;
    }

    /** @return array<int, string> */
    private function wrapText(string $text, int $maxChars): array
    {
        // Kept for compatibility; prefer width-based wrapping for body copy.
        return $this->wrapTextToWidth($text, max(120.0, $maxChars * 5.0), self::BODY_FONT_SIZE);
    }

    private function nextSignatureImageName(): string
    {
        $this->signatureImageCounter++;

        return 'Signature'.$this->signatureImageCounter;
    }

    private function jpegBytes(FileAsset $asset): ?string
    {
        $bytes = $this->readAssetBytes($asset);
        if ($bytes === null) {
            return null;
        }

        return $this->normalizeToJpeg($bytes);
    }

    private function readAssetBytes(FileAsset $asset): ?string
    {
        try {
            $stream = $this->fileAssetStreams->handle($asset);
            $bytes = stream_get_contents($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_string($bytes) && $bytes !== '') {
                return $bytes;
            }
        } catch (\Throwable) {
            // Fall through to direct disk read for partially hydrated / pending assets.
        }

        if (! filled($asset->object_key)) {
            return null;
        }

        try {
            $disk = $this->storageResolver->diskFor(
                $asset->storage_provider,
                $asset->disk_name,
                $asset->storage_configuration_revision,
            );
            $bytes = $disk->get($asset->object_key);

            return is_string($bytes) && $bytes !== '' ? $bytes : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeToJpeg(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            $info = @getimagesizefromstring($bytes);
            if (is_array($info) && isset($info[2]) && (int) $info[2] === IMAGETYPE_JPEG) {
                return $bytes;
            }

            return null;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            imagedestroy($image);

            return null;
        }

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
