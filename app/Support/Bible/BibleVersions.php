<?php

namespace App\Support\Bible;

final class BibleVersions
{
    public const KJV = 'kjv';

    public const NIV = 'niv';

    public const RSV = 'rsv';

    public const AMP = 'amp';

    /**
     * @return list<array{id: string, abbreviation: string, name: string, license: string, available: bool}>
     */
    public static function catalog(): array
    {
        return array_map(function (array $meta): array {
            $available = self::isAvailable($meta['id']);

            return [
                ...$meta,
                'available' => $available,
                'license' => $available
                    ? (self::payloadMeta($meta['id'])['license'] ?? $meta['license'])
                    : $meta['license'],
                'name' => $available
                    ? (self::payloadMeta($meta['id'])['name'] ?? $meta['name'])
                    : $meta['name'],
            ];
        }, self::definitions());
    }

    public static function normalize(?string $version): string
    {
        $id = strtolower(trim((string) $version));
        if ($id === '') {
            return self::KJV;
        }

        foreach (self::definitions() as $meta) {
            if ($meta['id'] === $id) {
                return $id;
            }
        }

        return self::KJV;
    }

    public static function isAvailable(string $version): bool
    {
        return is_file(self::path(self::normalize($version)));
    }

    public static function path(string $version): string
    {
        return database_path('data/bible/'.self::normalize($version).'.json');
    }

    /**
     * @return array{id: string, abbreviation: string, name: string, license: string, available: bool}
     */
    public static function summary(string $version): array
    {
        $id = self::normalize($version);
        foreach (self::catalog() as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        return self::catalog()[0];
    }

    /**
     * @return array{version?: string, name?: string, license?: string}
     */
    public static function payloadMeta(string $version): array
    {
        $path = self::path($version);
        if (! is_file($path)) {
            return [];
        }

        try {
            /** @var array{version?: string, name?: string, license?: string} $decoded */
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            return [
                'version' => $decoded['version'] ?? $version,
                'name' => $decoded['name'] ?? null,
                'license' => $decoded['license'] ?? null,
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{id: string, abbreviation: string, name: string, license: string}>
     */
    private static function definitions(): array
    {
        return [
            [
                'id' => self::KJV,
                'abbreviation' => 'KJV',
                'name' => 'King James Version',
                'license' => 'Public domain',
            ],
            [
                'id' => self::NIV,
                'abbreviation' => 'NIV',
                'name' => 'New International Version',
                'license' => 'Licensed text file required',
            ],
            [
                'id' => self::RSV,
                'abbreviation' => 'RSV',
                'name' => 'Revised Standard Version',
                'license' => 'Licensed text file required',
            ],
            [
                'id' => self::AMP,
                'abbreviation' => 'AMP',
                'name' => 'Amplified Bible',
                'license' => 'Licensed text file required',
            ],
        ];
    }
}
