<?php

namespace App\Support\Church;

final class ChurchLeadershipCatalog
{
    public const MAX_ACTIVE_LEADERS_PER_CHURCH = 5;

    /** @var list<string> */
    public const TITLES = [
        'Senior Pastor',
        'Associate Pastor',
        'Assistant Pastor',
        'Elder',
        'Deacon',
        'Church Administrator',
        'Ministry Head',
    ];

    public static function isValidTitle(string $title): bool
    {
        return in_array($title, self::TITLES, true);
    }
}
