<?php

namespace App\Kca;

use App\Models\KcaOrientationStep;
use Illuminate\Support\Facades\Schema;

final class KcaOrientationStages
{
    /** @var list<string> */
    public const LEGACY = ['overview', 'rules', 'path', 'mentors'];

    /** @return list<string> */
    public static function all(): array
    {
        if (! Schema::hasTable('kca_orientation_steps')) {
            return self::LEGACY;
        }

        $slugs = KcaOrientationStep::query()
            ->where('is_active', true)
            ->orderBy('sequence')
            ->orderBy('id')
            ->pluck('slug')
            ->all();

        return $slugs !== [] ? $slugs : self::LEGACY;
    }

    public static function isValid(string $stage): bool
    {
        return in_array($stage, self::all(), true);
    }
}
