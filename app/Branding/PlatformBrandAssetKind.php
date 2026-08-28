<?php

namespace App\Branding;

enum PlatformBrandAssetKind: string
{
    case Logo = 'logo';
    case Favicon = 'favicon';

    /** @return list<string> */
    public function allowedMimeTypes(): array
    {
        $images = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];

        return $this === self::Favicon
            ? [...$images, 'image/x-icon', 'image/vnd.microsoft.icon']
            : $images;
    }

    public function purpose(): string
    {
        return match ($this) {
            self::Logo => 'branding.logo',
            self::Favicon => 'branding.favicon',
        };
    }

    public function column(): string
    {
        return match ($this) {
            self::Logo => 'logo_file_asset_id',
            self::Favicon => 'favicon_file_asset_id',
        };
    }
}
