<?php

namespace App\Support\Bible;

final class BibleReadingPlanGenerator
{
    public const YEAR_1 = 'year_1';

    public const YEAR_2 = 'year_2';

    public const YEAR_3 = 'year_3';

    /**
     * @return list<array{code: string, name: string, days: int, description: string}>
     */
    public static function summaries(): array
    {
        return [
            [
                'code' => self::YEAR_1,
                'name' => 'Bible in 1 year',
                'days' => 365,
                'description' => 'About three to four chapters each day.',
            ],
            [
                'code' => self::YEAR_2,
                'name' => 'Bible in 2 years',
                'days' => 730,
                'description' => 'About one to two chapters each day.',
            ],
            [
                'code' => self::YEAR_3,
                'name' => 'Bible in 3 years',
                'days' => 1095,
                'description' => 'About one chapter each day.',
            ],
        ];
    }

    /**
     * @return array{code: string, name: string, days: int, description: string, passages_per_day: list<list<array{book_id: string, book_slug: string, book_name: string, chapter: int}>>}|null
     */
    public static function plan(string $code): ?array
    {
        $summary = null;
        foreach (self::summaries() as $item) {
            if ($item['code'] === $code) {
                $summary = $item;
                break;
            }
        }
        if ($summary === null) {
            return null;
        }

        $books = [];
        foreach (BibleCanon::books() as $book) {
            $books[$book['id']] = $book;
        }

        $sequence = BibleCanon::chapterSequence();
        $totalChapters = count($sequence);
        $days = $summary['days'];
        $base = intdiv($totalChapters, $days);
        $remainder = $totalChapters % $days;
        $passages = [];
        $offset = 0;

        for ($day = 0; $day < $days; $day++) {
            $count = $base + ($day < $remainder ? 1 : 0);
            $slice = array_slice($sequence, $offset, $count);
            $offset += $count;
            $passages[] = array_map(function (array $item) use ($books): array {
                $book = $books[$item['book_id']];

                return [
                    'book_id' => $book['id'],
                    'book_slug' => $book['slug'],
                    'book_name' => $book['name'],
                    'chapter' => $item['chapter'],
                ];
            }, $slice);
        }

        return [
            ...$summary,
            'passages_per_day' => $passages,
        ];
    }

    public static function isValidCode(string $code): bool
    {
        return self::plan($code) !== null;
    }
}
