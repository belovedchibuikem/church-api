<?php

namespace App\Support\Kca;

class KcaCertificatePdfRenderer
{
    public function render(string $holder, string $certificateNumber, string $issuedOn): string
    {
        $lines = [
            $this->pdfText('KINGDOM CHANGE AGENTS'),
            $this->pdfText('Certificate of Completion'),
            $this->pdfText($this->sanitize($holder)),
            $this->pdfText('Certificate '.$this->sanitize($certificateNumber)),
            $this->pdfText('Issued '.$this->sanitize($issuedOn)),
        ];
        $content = "BT /F1 16 Tf 72 720 Td ({$lines[0]}) Tj 0 -28 Td /F1 14 Tf ({$lines[1]}) Tj 0 -36 Td /F1 18 Tf ({$lines[2]}) Tj 0 -32 Td /F1 12 Tf ({$lines[3]}) Tj 0 -20 Td ({$lines[4]}) Tj ET";
        $length = strlen($content);

        $objects = [
            "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n",
            "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj\n",
            "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>endobj\n",
            "4 0 obj<< /Length {$length} >>stream\n{$content}\nendstream\nendobj\n",
            "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        for ($i = 1; $i <= 5; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function sanitize(string $value): string
    {
        $clean = preg_replace('/[^\x20-\x7E]/', ' ', $value) ?? '';

        return trim($clean) === '' ? 'Member' : $clean;
    }

    private function pdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
