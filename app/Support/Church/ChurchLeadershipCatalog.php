<?php

namespace App\Support\Church;

final class ChurchLeadershipCatalog
{
    public const MAX_ACTIVE_LEADERS_PER_CHURCH = 25;

    /** @var list<string> */
    public const TITLES = [
        'Senior Pastor',
        'Resident Pastor',
        'Associate Pastor',
        'Assistant Pastor',
        'Minister',
        'Evangelist',
        'Prophet',
        'Elder',
        'Deacon',
        'Deaconess',
        'President',
        'Church Administrator',
        'Ministry Head',
    ];

    public static function isValidTitle(string $title): bool
    {
        return in_array($title, self::TITLES, true);
    }
}
