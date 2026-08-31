<?php

namespace App\Admin;

enum AdminDashboardModule: string
{
    case Global = 'global';
    case Geography = 'geography';
    case HomeChurches = 'home-churches';
    case Church = 'church';
    case People = 'people';
    case Kca = 'kca';
    case Mission = 'mission';
    case Press = 'press';
    case Finance = 'finance';
    case Communications = 'communications';
    case Reports = 'reports';
    case Security = 'security';
    case Safeguarding = 'safeguarding';

    /** Primary permission recorded on access decisions for this module. */
    public function permission(): string
    {
        return AdminDashboardPermissionCatalog::acceptablePermissions($this)[0];
    }

    public static function fromRoute(string $module): self
    {
        return self::tryFrom($module) ?? throw new \InvalidArgumentException('Unknown dashboard module.');
    }
}
