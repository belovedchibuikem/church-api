<?php

namespace App\Support\Bible;

final class BibleReadingPlanGenerator
{
    public const MONTH_3 = 'month_3';

    public const MONTH_6 = 'month_6';

    public const YEAR_1 = 'year_1';

    public const YEAR_2 = 'year_2';

    public const YEAR_3 = 'year_3';

    public const MIN_CUSTOM_DAYS = 30;

    public const MAX_CUSTOM_DAYS = 1095;

    /**
     * @return list<array{code: string, name: string, days: int, description: string, kind: string}>
     */
    public static function summaries(): array
    {
        return [
            self::preset(self::MONTH_3, 'Bible in 3 months', 90, 'A faster pace — several chapters each day.'),
            self::preset(self::MONTH_6, 'Bible in 6 months', 180, 'About six to seven chapters each day.'),
            self::preset(self::YEAR_1, 'Bible in 1 year', 365, 'About three to four chapters each day.'),
            self::preset(self::YEAR_2, 'Bible in 2 years', 730, 'About one to two chapters each day.'),
            self::preset(self::YEAR_3, 'Bible in 3 years', 1095, 'About one chapter each day.'),
        ];
    }

    public static function customCode(int $days): string
    {
        return 'days_'.$days;
    }

    /**
     * @return array{code: string, name: string, days: int, description: string, kind: string, passages_per_day: list<list<array{book_id: string, book_slug: string, book_name: string, chapter: int}>>}|null
     */
    public static function plan(string $code): ?array
    {
        $summary = self::resolveSummary($code);
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
        return self::resolveSummary($code) !== null;
    }

    /**
     * @return array{code: string, name: string, days: int, description: string, kind: string}|null
     */
    public static function resolveSummary(string $code): ?array
    {
        foreach (self::summaries() as $item) {
            if ($item['code'] === $code) {
                return $item;
            }
        }

        if (preg_match('/^days_(\d+)$/', $code, $matches) === 1) {
            $days = (int) $matches[1];
            if ($days < self::MIN_CUSTOM_DAYS || $days > self::MAX_CUSTOM_DAYS) {
                return null;
            }

            return [
                'code' => $code,
                'name' => 'Custom '.$days.'-day plan',
                'days' => $days,
                'description' => 'Finish the whole Bible in '.$days.' days at a pace you chose.',
                'kind' => 'custom',
            ];
        }

        return null;
    }

    /**
     * @return array{code: string, name: string, days: int, description: string, kind: string}
     */
    private static function preset(string $code, string $name, int $days, string $description): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'days' => $days,
            'description' => $description,
            'kind' => 'preset',
        ];
    }
}
