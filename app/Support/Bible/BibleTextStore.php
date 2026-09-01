<?php

namespace App\Support\Bible;

use RuntimeException;

final class BibleTextStore
{
    /** @var array{version: string, name: string, license: string, books: array<string, list<list<string>>>}|null */
    private static ?array $payload = null;

    public static function version(): array
    {
        $payload = self::payload();

        return [
            'id' => $payload['version'],
            'name' => $payload['name'],
            'license' => $payload['license'],
        ];
    }

    /**
     * @return array{book: array<string, mixed>, chapter: int, verse_count: int, verses: list<array{verse: int, text: string}>}
     */
    public static function chapter(string $bookKey, int $chapter): array
    {
        $book = BibleCanon::bookByIdOrSlug($bookKey);
        if ($book === null || $chapter < 1 || $chapter > $book['chapters']) {
            throw new RuntimeException('Unknown Bible chapter.');
        }

        $verses = self::payload()['books'][$book['id']][$chapter - 1] ?? null;
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
                'testament' => $book['testament'],
                'chapters' => $book['chapters'],
            ],
            'chapter' => $chapter,
            'verse_count' => count($rows),
            'verses' => $rows,
            'previous' => self::adjacent($book['id'], $chapter, -1),
            'next' => self::adjacent($book['id'], $chapter, 1),
            'version' => self::version(),
        ];
    }

    /**
     * @return list<array{book_id: string, book_slug: string, book_name: string, chapter: int, verse: int, text: string, reference: string}>
     */
    public static function search(string $query, int $limit = 20): array
    {
        $reference = BibleCanon::parseReference($query);
        if ($reference !== null) {
            $chapter = self::chapter($reference['book']['id'], $reference['chapter']);
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
        if (mb_strlen($needle) < 3) {
            return [];
        }

        $matches = [];
        $books = [];
        foreach (BibleCanon::books() as $book) {
            $books[$book['id']] = $book;
        }

        foreach (self::payload()['books'] as $bookId => $chapters) {
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
    private static function payload(): array
    {
        if (self::$payload !== null) {
            return self::$payload;
        }

        $path = database_path('data/bible/kjv.json');
        if (! is_file($path)) {
            throw new RuntimeException('The King James Bible text file is missing.');
        }

        /** @var array{version: string, name: string, license: string, books: array<string, list<list<string>>>} $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::$payload = $decoded;

        return self::$payload;
    }
}
