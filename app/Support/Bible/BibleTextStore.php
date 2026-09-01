<?php

namespace App\Support\Bible;

use RuntimeException;

final class BibleTextStore
{
    /** @var array<string, array{version: string, name: string, license: string, books: array<string, list<list<string>>>}> */
    private static array $payloads = [];

    /**
     * @return array{id: string, abbreviation: string, name: string, license: string, available: bool}
     */
    public static function version(?string $version = null): array
    {
        return BibleVersions::summary($version ?? BibleVersions::KJV);
    }

    /**
     * @return array{book: array<string, mixed>, chapter: int, verse_count: int, verses: list<array{verse: int, text: string}>}
     */
    public static function chapter(string $bookKey, int $chapter, ?string $version = null): array
    {
        $book = BibleCanon::bookByIdOrSlug($bookKey);
        if ($book === null || $chapter < 1 || $chapter > $book['chapters']) {
            throw new RuntimeException('Unknown Bible chapter.');
        }

        $payload = self::payload($version);
        $verses = $payload['books'][$book['id']][$chapter - 1] ?? null;
        if (! is_array($verses)) {
            throw new RuntimeException('Bible text is not available for this chapter.');
        }

        $rows = [];
        foreach (array_values($verses) as $index => $text) {
            $rows[] = ['verse' => $index + 1, 'text' => $text];
        }

        return [
            'book' => [
                'id' => $book['id'],
                'slug' => $book['slug'],
                'name' => $book['name'],
                'abbrev' => strtoupper($book['id']),
                'testament' => $book['testament'],
                'chapters' => $book['chapters'],
            ],
            'chapter' => $chapter,
            'verse_count' => count($rows),
            'verses' => $rows,
            'previous' => self::adjacent($book['id'], $chapter, -1),
            'next' => self::adjacent($book['id'], $chapter, 1),
            'version' => self::version($version),
        ];
    }

    /**
     * @return list<array{book_id: string, book_slug: string, book_name: string, chapter: int, verse: int, text: string, reference: string}>
     */
    public static function search(string $query, int $limit = 20, ?string $version = null): array
    {
        $reference = BibleCanon::parseReference($query);
        if ($reference !== null) {
            $chapter = self::chapter($reference['book']['id'], $reference['chapter'], $version);
            if ($reference['verse'] !== null) {
                foreach ($chapter['verses'] as $verse) {
                    if ($verse['verse'] === $reference['verse']) {
                        return [[
                            'book_id' => $chapter['book']['id'],
                            'book_slug' => $chapter['book']['slug'],
                            'book_name' => $chapter['book']['name'],
                            'chapter' => $chapter['chapter'],
                            'verse' => $verse['verse'],
                            'text' => $verse['text'],
                            'reference' => $chapter['book']['name'].' '.$chapter['chapter'].':'.$verse['verse'],
                        ]];
                    }
                }

                return [];
            }

            return array_slice(array_map(fn (array $verse): array => [
                'book_id' => $chapter['book']['id'],
                'book_slug' => $chapter['book']['slug'],
                'book_name' => $chapter['book']['name'],
                'chapter' => $chapter['chapter'],
                'verse' => $verse['verse'],
                'text' => $verse['text'],
                'reference' => $chapter['book']['name'].' '.$chapter['chapter'].':'.$verse['verse'],
            ], $chapter['verses']), 0, $limit);
        }

        $needle = mb_strtolower(trim($query));
        if (mb_strlen($needle) < 2) {
            return [];
        }

        $matches = [];
        $books = [];
        foreach (BibleCanon::books() as $book) {
            $books[$book['id']] = $book;
        }

        foreach (self::payload($version)['books'] as $bookId => $chapters) {
            $book = $books[$bookId] ?? null;
            if ($book === null) {
                continue;
            }
            foreach ($chapters as $chapterIndex => $verses) {
                foreach ($verses as $verseIndex => $text) {
                    if (! str_contains(mb_strtolower($text), $needle)) {
                        continue;
                    }
                    $matches[] = [
                        'book_id' => $book['id'],
                        'book_slug' => $book['slug'],
                        'book_name' => $book['name'],
                        'chapter' => $chapterIndex + 1,
                        'verse' => $verseIndex + 1,
                        'text' => $text,
                        'reference' => $book['name'].' '.($chapterIndex + 1).':'.($verseIndex + 1),
                    ];
                    if (count($matches) >= $limit) {
                        return $matches;
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * @return array{book_id: string, book_slug: string, book_name: string, chapter: int}|null
     */
    private static function adjacent(string $bookId, int $chapter, int $direction): ?array
    {
        $sequence = BibleCanon::chapterSequence();
        foreach ($sequence as $index => $item) {
            if ($item['book_id'] === $bookId && $item['chapter'] === $chapter) {
                $target = $sequence[$index + $direction] ?? null;
                if ($target === null) {
                    return null;
                }
                $book = BibleCanon::bookByIdOrSlug($target['book_id']);

                return [
                    'book_id' => $book['id'],
                    'book_slug' => $book['slug'],
                    'book_name' => $book['name'],
                    'chapter' => $target['chapter'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array{version: string, name: string, license: string, books: array<string, list<list<string>>>}
     */
    private static function payload(?string $version): array
    {
        $id = BibleVersions::normalize($version);
        if (isset(self::$payloads[$id])) {
            return self::$payloads[$id];
        }

        $path = BibleVersions::path($id);
        if (! is_file($path)) {
            throw new RuntimeException(
                BibleVersions::summary($id)['abbreviation'].' is not installed on this server. Add database/data/bible/'.$id.'.json (licensed text) or keep reading in KJV.',
            );
        }

        /** @var array{version: string, name: string, license: string, books: array<string, list<list<string>>>} $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::$payloads[$id] = $decoded;

        return self::$payloads[$id];
    }
}
