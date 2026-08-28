<?php

namespace App\Demo;

final class DemoPngFactory
{
    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    public static function make(array $rgb, int $width = 640, int $height = 360): string
    {
        $width = max(32, min($width, 720));
        $height = max(32, min($height, 400));
        [$baseR, $baseG, $baseB] = $rgb;
        $raw = '';

        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00";
            for ($x = 0; $x < $width; $x++) {
                $wave = (int) (18 * sin($x / 28) + 14 * sin($y / 22));
                $raw .= chr(self::clamp($baseR + $wave))
                    .chr(self::clamp($baseG + $wave))
                    .chr(self::clamp($baseB + (int) ($wave * 0.6)));
            }
        }

        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n";
        $png .= self::chunk('IHDR', $ihdr);
        $png .= self::chunk('IDAT', gzcompress($raw, 9) ?: $raw);
        $png .= self::chunk('IEND', '');

        return $png;
    }

    private static function clamp(int $value): int
    {
        return max(0, min(255, $value));
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
