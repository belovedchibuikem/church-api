<?php

namespace App\Support\Livestream;

use InvalidArgumentException;

final class YoutubeLivestreamUrl
{
    public static function parseVideoId(string $input): string
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            throw new InvalidArgumentException('A YouTube URL or video id is required.');
        }

        if (preg_match('/\A[A-Za-z0-9_-]{11}\z/', $trimmed) === 1) {
            return $trimmed;
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts)) {
            throw new InvalidArgumentException('Unable to parse the YouTube URL.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        if (str_contains($host, 'youtu.be')) {
            $id = ltrim($path, '/');
            if (preg_match('/\A[A-Za-z0-9_-]{11}\z/', $id) === 1) {
                return $id;
            }
        }

        if (isset($query['v']) && preg_match('/\A[A-Za-z0-9_-]{11}\z/', (string) $query['v']) === 1) {
            return (string) $query['v'];
        }

        if (preg_match('#/(embed|live|shorts)/([A-Za-z0-9_-]{11})#', $path, $matches) === 1) {
            return $matches[2];
        }

        throw new InvalidArgumentException('Provide a valid YouTube watch, live, shorts, or embed URL.');
    }

    public static function watchUrl(string $videoId): string
    {
        return 'https://www.youtube.com/watch?v='.$videoId;
    }

    public static function embedUrl(string $videoId): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.$videoId.'?autoplay=1&rel=0&modestbranding=1';
    }

    public static function thumbnailUrl(string $videoId): string
    {
        return 'https://i.ytimg.com/vi/'.$videoId.'/hqdefault.jpg';
    }
}
