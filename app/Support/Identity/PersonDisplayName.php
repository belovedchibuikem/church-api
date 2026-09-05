<?php

namespace App\Support\Identity;

use App\Models\Person;

final class PersonDisplayName
{
    /**
     * Constrained eager-load paths for profile + linked user display fields.
     *
     * @return array<int, string>
     */
    public static function eager(string $relation = 'person'): array
    {
        return [
            $relation.':id,public_id',
            $relation.'.profile:id,person_id,given_name,middle_name,family_name,preferred_name,phone',
            $relation.'.user:id,person_id,name,email',
        ];
    }

    public static function of(?Person $person): string
    {
        if ($person === null) {
            return '';
        }

        $profile = $person->profile;
        $preferred = trim((string) ($profile?->preferred_name ?? ''));
        if ($preferred !== '') {
            return $preferred;
        }

        $full = trim(implode(' ', array_filter([
            $profile?->given_name,
            $profile?->family_name,
        ], static fn (?string $part): bool => filled($part))));
        if ($full !== '') {
            return $full;
        }

        return trim((string) ($person->user?->name ?? ''));
    }

    public static function email(?Person $person): string
    {
        return trim((string) ($person?->user?->email ?? ''));
    }

    public static function phone(?Person $person): string
    {
        return trim((string) ($person?->profile?->phone ?? ''));
    }
}
