<?php

namespace App\Files;

use App\Exceptions\FileAssetValidationException;
use App\Files\Contracts\FileContentPolicy;
use App\Files\Data\InspectedFile;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ConfiguredFileContentPolicy implements FileContentPolicy
{
    public function inspect(UploadedFile $file): InspectedFile
    {
        if (! $file->isValid()) {
            throw new FileAssetValidationException('upload_invalid');
        }

        $path = $file->getRealPath();
        $byteSize = $file->getSize();

        if (! is_string($path) || $path === '' || ! is_int($byteSize) || $byteSize < 0) {
            throw new FileAssetValidationException('upload_unreadable');
        }

        $maximumBytes = (int) config('file_assets.maximum_bytes');

        if ($maximumBytes < 1 || $byteSize > $maximumBytes) {
            throw new FileAssetValidationException('file_too_large');
        }

        $detectedMimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $allowedMimeTypes = config('file_assets.allowed_mime_types');

        if (
            ! is_string($detectedMimeType)
            || ! is_array($allowedMimeTypes)
            || ! in_array($detectedMimeType, $allowedMimeTypes, true)
        ) {
            throw new FileAssetValidationException('mime_type_not_allowed');
        }

        $sha256 = hash_file('sha256', $path);

        if (! is_string($sha256)) {
            throw new FileAssetValidationException('upload_unreadable');
        }

        return new InspectedFile(
            detectedMimeType: $detectedMimeType,
            byteSize: $byteSize,
            sha256: $sha256,
            sanitizedOriginalFilename: $this->sanitizeFilename($file->getClientOriginalName()),
        );
    }

    private function sanitizeFilename(string $filename): ?string
    {
        $sanitized = Str::of($filename)
            ->replace('\\', '/')
            ->afterLast('/')
            ->replaceMatches('/[\x00-\x1F\x7F]/u', '')
            ->replaceMatches('/[^\pL\pN._ -]+/u', '_')
            ->squish()
            ->trim('. ')
            ->limit(200, '')
            ->toString();

        return $sanitized === '' ? null : $sanitized;
    }
}
