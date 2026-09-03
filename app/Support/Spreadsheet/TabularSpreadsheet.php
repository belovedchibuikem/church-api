<?php

namespace App\Support\Spreadsheet;

use InvalidArgumentException;
use ZipArchive;

/**
 * Lightweight CSV / XLSX tabular reader-writer (no Composer spreadsheet deps).
 */
final class TabularSpreadsheet
{
    /**
     * @return list<array<string, string>>
     */
    public static function readAssociativeRows(string $absolutePath, string $originalFilename): array
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        $matrix = match ($extension) {
            'csv', 'txt' => self::readCsvMatrix($absolutePath),
            'xlsx' => self::readXlsxMatrix($absolutePath),
            default => throw new InvalidArgumentException('Unsupported file type. Upload a CSV or Excel (.xlsx) file.'),
        };

        if ($matrix === []) {
            throw new InvalidArgumentException('The spreadsheet is empty.');
        }

        $headers = array_map(static fn (mixed $value): string => self::normalizeHeader((string) $value), $matrix[0]);
        if ($headers === [] || implode('', $headers) === '') {
            throw new InvalidArgumentException('The spreadsheet is missing a header row.');
        }

        $rows = [];
        for ($i = 1, $count = count($matrix); $i < $count; $i++) {
            $cells = $matrix[$i];
            $assoc = [];
            $hasValue = false;
            foreach ($headers as $columnIndex => $header) {
                if ($header === '') {
                    continue;
                }
                $raw = isset($cells[$columnIndex]) ? trim((string) $cells[$columnIndex]) : '';
                if ($raw !== '') {
                    $hasValue = true;
                }
                $assoc[$header] = $raw;
            }
            if ($hasValue) {
                $rows[] = $assoc;
            }
        }

        if ($rows === []) {
            throw new InvalidArgumentException('The spreadsheet has headers but no data rows.');
        }

        return $rows;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, string|null>>  $rows
     */
    public static function streamCsv(string $filename, array $headers, array $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            // UTF-8 BOM so Excel opens accents correctly
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $header) {
                    $line[] = (string) ($row[$header] ?? '');
                }
                fputcsv($handle, $line);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return list<list<string>>
     */
    private static function readCsvMatrix(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Unable to read the uploaded CSV file.');
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $matrix = [];
        while (($row = fgetcsv($handle)) !== false) {
            /** @var list<string|null> $row */
            $matrix[] = array_map(static fn ($cell): string => (string) ($cell ?? ''), $row);
        }
        fclose($handle);

        return $matrix;
    }

    /**
     * @return list<list<string>>
     */
    private static function readXlsxMatrix(string $absolutePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            throw new InvalidArgumentException('Unable to open the Excel (.xlsx) file.');
        }

        $sharedStrings = self::parseSharedStrings((string) $zip->getFromName('xl/sharedStrings.xml'));
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            // Some workbooks use different first-sheet names; fall back to workbook relations.
            $sheetXml = self::firstWorksheetXml($zip);
        }
        $zip->close();

        if ($sheetXml === false || $sheetXml === '') {
            throw new InvalidArgumentException('The Excel file has no readable worksheet.');
        }

        return self::parseSheetXml($sheetXml, $sharedStrings);
    }

    /**
     * @return list<string>
     */
    private static function parseSharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($document === false) {
            return [];
        }

        $document->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];
        foreach ($document->xpath('//m:si') ?: [] as $si) {
            $parts = $si->xpath('.//m:t') ?: [];
            $text = '';
            foreach ($parts as $part) {
                $text .= (string) $part;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private static function firstWorksheetXml(ZipArchive $zip): string|false
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1) {
                return $zip->getFromIndex($i);
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<list<string>>
     */
    private static function parseSheetXml(string $sheetXml, array $sharedStrings): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($sheetXml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($document === false) {
            throw new InvalidArgumentException('The Excel worksheet could not be parsed.');
        }

        $document->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        $maxColumn = 0;

        foreach ($document->xpath('//m:sheetData/m:row') ?: [] as $rowNode) {
            $rowIndex = ((int) $rowNode['r']) - 1;
            if ($rowIndex < 0) {
                $rowIndex = count($rows);
            }
            while (count($rows) <= $rowIndex) {
                $rows[] = [];
            }
            foreach ($rowNode->c ?? [] as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $columnIndex = self::columnIndexFromRef($ref);
                $maxColumn = max($maxColumn, $columnIndex);
                $type = (string) ($cell['t'] ?? '');
                $value = '';
                if ($type === 's') {
                    $sharedIndex = (int) ($cell->v ?? 0);
                    $value = $sharedStrings[$sharedIndex] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } else {
                    $value = (string) ($cell->v ?? '');
                }
                $rows[$rowIndex][$columnIndex] = $value;
            }
        }

        $matrix = [];
        foreach ($rows as $sparse) {
            $line = [];
            for ($c = 0; $c <= $maxColumn; $c++) {
                $line[] = (string) ($sparse[$c] ?? '');
            }
            $matrix[] = $line;
        }

        return $matrix;
    }

    private static function columnIndexFromRef(string $ref): int
    {
        if (preg_match('/^([A-Z]+)/i', $ref, $matches) !== 1) {
            return 0;
        }
        $letters = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    private static function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = str_replace([' ', '-'], '_', $header);

        return strtolower($header);
    }
}
