<?php

namespace App\Files;

use App\Exceptions\FileAssetUnavailableException;
use App\Files\Queries\OpenFileAssetStreamQuery;
use App\Models\FileAsset;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileAssetStreamResponse
{
    public function __construct(
        private readonly OpenFileAssetStreamQuery $fileAssetStreams,
    ) {}

    public function handle(FileAsset $fileAsset, bool $asAttachment = false): StreamedResponse
    {
        try {
            $stream = $this->fileAssetStreams->handle($fileAsset);
        } catch (FileAssetUnavailableException) {
            abort(404);
        }

        $filename = $this->filename($fileAsset);
        $mimeType = $fileAsset->detected_mime_type;

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, 200, [
            'Content-Type' => is_string($mimeType) && $mimeType !== ''
                ? $mimeType
                : 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $asAttachment ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
                $filename,
                $this->asciiFilename($filename),
            ),
        ]);
    }

    private function filename(FileAsset $fileAsset): string
    {
        $metadata = is_array($fileAsset->metadata) ? $fileAsset->metadata : [];
        $originalFilename = $metadata['original_filename'] ?? null;
        $candidate = is_string($originalFilename) && $originalFilename !== ''
            ? $originalFilename
            : 'file';

        $sanitized = Str::of($candidate)
            ->replace('\\', '/')
            ->afterLast('/')
            ->replaceMatches('/[\x00-\x1F\x7F]/u', '')
            ->replaceMatches('/[^\pL\pN._ -]+/u', '_')
            ->squish()
            ->trim('. ')
            ->limit(200, '')
            ->toString();

        return $sanitized === '' ? 'file' : $sanitized;
    }

    private function asciiFilename(string $filename): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?? 'file';
        $ascii = trim($ascii, '. ');

        return $ascii === '' ? 'file' : $ascii;
    }
}
