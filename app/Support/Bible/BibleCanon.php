<?php

namespace App\Support\Bible;

final class BibleCanon
{
    /**
     * Protestant canon: 66 books, 1,189 chapters.
     *
     * @return list<array{id: string, slug: string, name: string, testament: 'ot'|'nt', chapters: int, aliases: list<string>}>
     */
    public static function books(): array
    {
        return [
            self::book('gen', 'genesis', 'Genesis', 'ot', 50, ['gn', 'gen.']),
            self::book('exo', 'exodus', 'Exodus', 'ot', 40, ['ex', 'exod']),
            self::book('lev', 'leviticus', 'Leviticus', 'ot', 27, ['lv', 'lev.']),
            self::book('num', 'numbers', 'Numbers', 'ot', 36, ['nm', 'num.']),
            self::book('deu', 'deuteronomy', 'Deuteronomy', 'ot', 34, ['dt', 'deut']),
            self::book('jos', 'joshua', 'Joshua', 'ot', 24, ['josh']),
            self::book('jdg', 'judges', 'Judges', 'ot', 21, ['judg', 'jdgs']),
            self::book('rut', 'ruth', 'Ruth', 'ot', 4, ['rt']),
            self::book('1sa', '1-samuel', '1 Samuel', 'ot', 31, ['1sam', '1 sam', 'i samuel']),
            self::book('2sa', '2-samuel', '2 Samuel', 'ot', 24, ['2sam', '2 sam', 'ii samuel']),
            self::book('1ki', '1-kings', '1 Kings', 'ot', 22, ['1kgs', '1 kings', 'i kings']),
            self::book('2ki', '2-kings', '2 Kings', 'ot', 25, ['2kgs', '2 kings', 'ii kings']),
            self::book('1ch', '1-chronicles', '1 Chronicles', 'ot', 29, ['1chr', '1 chron']),
            self::book('2ch', '2-chronicles', '2 Chronicles', 'ot', 36, ['2chr', '2 chron']),
            self::book('ezr', 'ezra', 'Ezra', 'ot', 10, []),
            self::book('neh', 'nehemiah', 'Nehemiah', 'ot', 13, ['ne']),
            self::book('est', 'esther', 'Esther', 'ot', 10, ['es']),
            self::book('job', 'job', 'Job', 'ot', 42, []),
            self::book('psa', 'psalms', 'Psalms', 'ot', 150, ['ps', 'psalm', 'pss']),
            self::book('pro', 'proverbs', 'Proverbs', 'ot', 31, ['prv', 'prov']),
            self::book('ecc', 'ecclesiastes', 'Ecclesiastes', 'ot', 12, ['eccl', 'qoh']),
            self::book('sng', 'song-of-solomon', 'Song of Solomon', 'ot', 8, ['song', 'sos', 'canticles', 'song of songs']),
            self::book('isa', 'isaiah', 'Isaiah', 'ot', 66, ['is']),
            self::book('jer', 'jeremiah', 'Jeremiah', 'ot', 52, ['jr']),
            self::book('lam', 'lamentations', 'Lamentations', 'ot', 5, []),
            self::book('ezk', 'ezekiel', 'Ezekiel', 'ot', 48, ['eze']),
            self::book('dan', 'daniel', 'Daniel', 'ot', 12, ['dn']),
            self::book('hos', 'hosea', 'Hosea', 'ot', 14, ['ho']),
            self::book('jol', 'joel', 'Joel', 'ot', 3, ['jl']),
            self::book('amo', 'amos', 'Amos', 'ot', 9, ['am']),
            self::book('oba', 'obadiah', 'Obadiah', 'ot', 1, ['ob']),
            self::book('jon', 'jonah', 'Jonah', 'ot', 4, []),
            self::book('mic', 'micah', 'Micah', 'ot', 7, ['mi']),
            self::book('nam', 'nahum', 'Nahum', 'ot', 3, ['na']),
            self::book('hab', 'habakkuk', 'Habakkuk', 'ot', 3, ['hk', 'hab.']),
            self::book('zep', 'zephaniah', 'Zephaniah', 'ot', 3, ['zp']),
            self::book('hag', 'haggai', 'Haggai', 'ot', 2, ['hg']),
            self::book('zec', 'zechariah', 'Zechariah', 'ot', 14, ['zc', 'zech']),
            self::book('mal', 'malachi', 'Malachi', 'ot', 4, ['ml']),
            self::book('mat', 'matthew', 'Matthew', 'nt', 28, ['mt', 'matt']),
            self::book('mrk', 'mark', 'Mark', 'nt', 16, ['mk']),
            self::book('luk', 'luke', 'Luke', 'nt', 24, ['lk']),
            self::book('jhn', 'john', 'John', 'nt', 21, ['jn', 'joh']),
            self::book('act', 'acts', 'Acts', 'nt', 28, ['ac']),
            self::book('rom', 'romans', 'Romans', 'nt', 16, ['ro', 'rm']),
            self::book('1co', '1-corinthians', '1 Corinthians', 'nt', 16, ['1cor', '1 cor']),
            self::book('2co', '2-corinthians', '2 Corinthians', 'nt', 13, ['2cor', '2 cor']),
            self::book('gal', 'galatians', 'Galatians', 'nt', 6, ['ga']),
            self::book('eph', 'ephesians', 'Ephesians', 'nt', 6, []),
            self::book('php', 'philippians', 'Philippians', 'nt', 4, ['phil']),
            self::book('col', 'colossians', 'Colossians', 'nt', 4, []),
            self::book('1th', '1-thessalonians', '1 Thessalonians', 'nt', 5, ['1thess', '1 th']),
            self::book('2th', '2-thessalonians', '2 Thessalonians', 'nt', 3, ['2thess', '2 th']),
            self::book('1ti', '1-timothy', '1 Timothy', 'nt', 6, ['1tim', '1 tim']),
            self::book('2ti', '2-timothy', '2 Timothy', 'nt', 4, ['2tim', '2 tim']),
            self::book('tit', 'titus', 'Titus', 'nt', 3, []),
            self::book('phm', 'philemon', 'Philemon', 'nt', 1, ['phlm']),
            self::book('heb', 'hebrews', 'Hebrews', 'nt', 13, []),
            self::book('jas', 'james', 'James', 'nt', 5, ['jm']),
            self::book('1pe', '1-peter', '1 Peter', 'nt', 5, ['1pet', '1 pet']),
            self::book('2pe', '2-peter', '2 Peter', 'nt', 3, ['2pet', '2 pet']),
            self::book('1jn', '1-john', '1 John', 'nt', 5, ['1 john', '1 jn']),
            self::book('2jn', '2-john', '2 John', 'nt', 1, ['2 john', '2 jn']),
            self::book('3jn', '3-john', '3 John', 'nt', 1, ['3 john', '3 jn']),
            self::book('jud', 'jude', 'Jude', 'nt', 1, []),
            self::book('rev', 'revelation', 'Revelation', 'nt', 22, ['re', 'rev.']),
        ];
    }

    /**
     * @return array{id: string, slug: string, name: string, testament: 'ot'|'nt', chapters: int, aliases: list<string>}
     */
    public static function bookByIdOrSlug(string $value): ?array
    {
        $needle = self::normalize($value);
        foreach (self::books() as $book) {
            if ($book['id'] === $needle || $book['slug'] === $needle) {
                return $book;
            }
        }

        return self::bookByAlias($value);
    }

    /**
     * @return array{id: string, slug: string, name: string, testament: 'ot'|'nt', chapters: int, aliases: list<string>}
     */
    public static function bookByAlias(string $value): ?array
    {
        $needle = self::normalize($value);
        $ranked = [];
        foreach (self::books() as $book) {
            $candidates = array_merge([$book['id'], $book['slug'], $book['name']], $book['aliases']);
            foreach ($candidates as $candidate) {
                $normalized = self::normalize((string) $candidate);
                if ($normalized === $needle) {
                    $ranked[] = [strlen($normalized), $book];
                }
            }
        }
        usort($ranked, fn (array $a, array $b): int => $b[0] <=> $a[0]);

        return $ranked[0][1] ?? null;
    }

    /**
     * @return list<array{book_id: string, chapter: int}>
     */
    public static function chapterSequence(): array
    {
        $sequence = [];
        foreach (self::books() as $book) {
            for ($chapter = 1; $chapter <= $book['chapters']; $chapter++) {
                $sequence[] = ['book_id' => $book['id'], 'chapter' => $chapter];
            }
        }

        return $sequence;
    }

    /**
     * @return array{book: array{id: string, slug: string, name: string, testament: 'ot'|'nt', chapters: int, aliases: list<string>}, chapter: int, verse: int|null}|null
     */
    public static function parseReference(string $query): ?array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^(?<book>.+?)\s+(?<chapter>\d+)(?::(?<verse>\d+))?$/u', $trimmed, $matches) !== 1) {
            $book = self::bookByIdOrSlug($trimmed);

            return $book === null ? null : ['book' => $book, 'chapter' => 1, 'verse' => null];
        }

        $book = self::bookByAlias($matches['book']);
        if ($book === null) {
            return null;
        }

        $chapter = (int) $matches['chapter'];
        if ($chapter < 1 || $chapter > $book['chapters']) {
            return null;
        }

        $verse = isset($matches['verse']) && $matches['verse'] !== '' ? (int) $matches['verse'] : null;

        return ['book' => $book, 'chapter' => $chapter, 'verse' => $verse];
    }

    public static function chapterCount(): int
    {
        return count(self::chapterSequence());
    }

    /**
     * @param  list<string>  $aliases
     * @return array{id: string, slug: string, name: string, testament: 'ot'|'nt', chapters: int, aliases: list<string>}
     */
    private static function book(string $id, string $slug, string $name, string $testament, int $chapters, array $aliases): array
    {
        return [
            'id' => $id,
            'slug' => $slug,
            'name' => $name,
            'testament' => $testament,
            'chapters' => $chapters,
            'aliases' => $aliases,
        ];
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['.', '_'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return str_replace(' ', '-', $value);
    }
}
