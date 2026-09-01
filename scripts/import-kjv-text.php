<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$target = $root.'/database/data/bible/kjv.json';
$sourceUrl = 'https://raw.githubusercontent.com/thiagobodruk/bible/master/json/en_kjv.json';
$sourceCache = $root.'/storage/app/kjv-src.json';

require $root.'/vendor/autoload.php';

use App\Support\Bible\BibleCanon;

$abbrevMap = [
    'gn' => 'gen',
    'ex' => 'exo',
    'lv' => 'lev',
    'nm' => 'num',
    'dt' => 'deu',
    'js' => 'jos',
    'jud' => 'jdg',
    'rt' => 'rut',
    '1sm' => '1sa',
    '2sm' => '2sa',
    '1kgs' => '1ki',
    '2kgs' => '2ki',
    '1ch' => '1ch',
    '2ch' => '2ch',
    'ezr' => 'ezr',
    'ne' => 'neh',
    'et' => 'est',
    'job' => 'job',
    'ps' => 'psa',
    'prv' => 'pro',
    'ec' => 'ecc',
    'so' => 'sng',
    'is' => 'isa',
    'jr' => 'jer',
    'lm' => 'lam',
    'ez' => 'ezk',
    'dn' => 'dan',
    'ho' => 'hos',
    'jl' => 'jol',
    'am' => 'amo',
    'ob' => 'oba',
    'jn' => 'jon',
    'mi' => 'mic',
    'na' => 'nam',
    'hk' => 'hab',
    'zp' => 'zep',
    'hg' => 'hag',
    'zc' => 'zec',
    'ml' => 'mal',
    'mt' => 'mat',
    'mk' => 'mrk',
    'lk' => 'luk',
    'jo' => 'jhn',
    'act' => 'act',
    'rm' => 'rom',
    '1co' => '1co',
    '2co' => '2co',
    'gl' => 'gal',
    'eph' => 'eph',
    'ph' => 'php',
    'cl' => 'col',
    '1ts' => '1th',
    '2ts' => '2th',
    '1tm' => '1ti',
    '2tm' => '2ti',
    'tt' => 'tit',
    'phm' => 'phm',
    'hb' => 'heb',
    'jm' => 'jas',
    '1pe' => '1pe',
    '2pe' => '2pe',
    '1jo' => '1jn',
    '2jo' => '2jn',
    '3jo' => '3jn',
    'jd' => 'jud',
    're' => 'rev',
];

$raw = is_file($sourceCache)
    ? (string) file_get_contents($sourceCache)
    : @file_get_contents($sourceUrl);
if ($raw !== false && str_starts_with($raw, "\xEF\xBB\xBF")) {
    $raw = substr($raw, 3);
}
if ($raw === false) {
    fwrite(STDERR, "Unable to download KJV JSON from {$sourceUrl}\n");
    exit(1);
}

$payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
if (! is_array($payload)) {
    fwrite(STDERR, "Unexpected KJV JSON shape.\n");
    exit(1);
}

$books = [];
foreach ($payload as $item) {
    if (! is_array($item) || ! isset($item['abbrev'], $item['chapters']) || ! is_array($item['chapters'])) {
        continue;
    }
    $id = $abbrevMap[strtolower((string) $item['abbrev'])] ?? null;
    if ($id === null) {
        fwrite(STDERR, "Unmapped abbrev: {$item['abbrev']}\n");
        exit(1);
    }
    $chapters = [];
    foreach ($item['chapters'] as $chapter) {
        if (! is_array($chapter)) {
            continue;
        }
        $chapters[] = array_values(array_map(static fn (mixed $verse): string => (string) $verse, $chapter));
    }
    $books[$id] = $chapters;
}

foreach (BibleCanon::books() as $book) {
    if (! isset($books[$book['id']])) {
        fwrite(STDERR, "Missing book {$book['id']}\n");
        exit(1);
    }
    if (count($books[$book['id']]) !== $book['chapters']) {
        fwrite(STDERR, "Chapter count mismatch for {$book['id']}\n");
        exit(1);
    }
}

$encoded = json_encode([
    'version' => 'kjv',
    'name' => 'King James Version',
    'license' => 'public-domain',
    'books' => $books,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
    fwrite(STDERR, "Unable to create bible data directory.\n");
    exit(1);
}

file_put_contents($target, $encoded);
fwrite(STDOUT, "Wrote {$target} (".strlen($encoded)." bytes)\n");
