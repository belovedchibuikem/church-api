<?php

namespace App\Branding;

use App\Media\PublicMediaUrl;
use App\Models\FileAsset;
use App\Models\PlatformBrandingConfiguration;

final class PlatformBrandingPresenter
{
    public const DEFAULT_APP_NAME = 'Family House Connect';

    public static function defaultAppName(): string
    {
        return self::DEFAULT_APP_NAME;
    }

    /**
     * @return array{
     *     app_name: string,
     *     logo_url: string|null,
     *     favicon_url: string|null,
     *     configured: bool,
     *     configuration_revision: int,
     *     logo: array<string, mixed>|null,
     *     favicon: array<string, mixed>|null
     * }
     */
    public static function toArray(?PlatformBrandingConfiguration $configuration): array
    {
        $configuration?->loadMissing(['logoFile', 'faviconFile']);
        $logo = $configuration?->logoFile;
        $favicon = $configuration?->faviconFile;

        return [
            'app_name' => filled($configuration?->app_name)
                ? (string) $configuration->app_name
                : self::defaultAppName(),
            'logo_url' => PublicMediaUrl::forAsset($logo),
            'favicon_url' => PublicMediaUrl::forAsset($favicon),
            'configured' => $configuration !== null,
            'configuration_revision' => (int) ($configuration?->configuration_revision ?? 0),
            'logo' => self::fileSummary($logo),
            'favicon' => self::fileSummary($favicon),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fileSummary(?FileAsset $file): ?array
    {
        if ($file === null) {
            return null;
        }

        $metadata = is_array($file->metadata) ? $file->metadata : [];

        return [
            'id' => $file->public_id,
            'detected_mime_type' => $file->detected_mime_type,
            'byte_size' => $file->byte_size,
            'original_filename' => $metadata['original_filename'] ?? null,
        ];
    }
}
