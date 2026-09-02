<?php

namespace App\Support\Pdf;

final class SimplePdfDocument
{
    /** @var array<int, string> */
    private array $pages = [];

    /** @var array<string, array{data: string, width: int, height: int}> */
    private array $images = [];

    public function addJpegImage(string $name, string $jpegBytes, int $width, int $height): void
    {
        $this->images[$name] = [
            'data' => $jpegBytes,
            'width' => $width,
            'height' => $height,
        ];
    }

    /** @param  array<int, string>  $operations */
    public function addPage(array $operations): void
    {
        $this->pages[] = implode("\n", $operations);
    }

    public function build(): string
    {
        if ($this->pages === []) {
            $this->pages[] = 'BT /F1 12 Tf 72 720 Td (Admission letter) Tj ET';
        }

        $objects = [];
        $objects[] = ''; // 0 unused
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pageRefs = [];
        $nextId = 3;
        $fontRegularId = $nextId++;
        $fontBoldId = $nextId++;
        $fontItalicId = $nextId++;
        $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >>';
        $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold >>';
        $objects[$fontItalicId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Italic >>';

        $imageIds = [];
        foreach ($this->images as $name => $image) {
            $imageIds[$name] = $nextId;
            $length = strlen($image['data']);
            $objects[$nextId] = "<< /Type /XObject /Subtype /Image /Width {$image['width']} /Height {$image['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$length} >>\nstream\n{$image['data']}\nendstream";
            $nextId++;
        }

        foreach ($this->pages as $content) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageRefs[] = "{$pageId} 0 R";

            $resources = "<< /Font << /F1 {$fontRegularId} 0 R /F2 {$fontBoldId} 0 R /F3 {$fontItalicId} 0 R >>";
            if ($imageIds !== []) {
                $xObjects = [];
                foreach ($imageIds as $name => $id) {
                    $xObjects[] = "/{$name} {$id} 0 R";
                }
                $resources .= ' /XObject << '.implode(' ', $xObjects).' >>';
            }
            $resources .= ' >>';

            $length = strlen($content);
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents {$contentId} 0 R /Resources {$resources} >>";
            $objects[$contentId] = "<< /Length {$length} >>\nstream\n{$content}\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageRefs).'] /Count '.count($pageRefs).' >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        for ($id = 1, $max = count($objects) - 1; $id <= $max; $id++) {
            if (! isset($objects[$id])) {
                continue;
            }
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$objects[$id]}\nendobj\n";
        }

        $xref = strlen($pdf);
        $size = count($offsets);
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= 'trailer<< /Size '.$size.' /Root 1 0 R >>'."\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    public static function escapeText(string $value): string
    {
        $clean = preg_replace('/[^\x20-\x7E]/', ' ', $value) ?? '';
        $clean = trim($clean) === '' ? '—' : trim($clean);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $clean);
    }
}
