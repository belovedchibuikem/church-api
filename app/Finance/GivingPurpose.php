<?php

namespace App\Finance;

final class GivingPurpose
{
    public const TITHE = 'tithe';

    public const OFFERING = 'offering';

    public const MISSIONS = 'missions';

    public const PROJECTS = 'projects';

    public const DONATION = 'donation';

    public const KCA = 'kca';

    public const PUBLICATION = 'publication';

    public const LEGACY = 'giving';

    public const PROOF_FILE_PURPOSE = 'payment.proof';

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return [
            self::TITHE,
            self::OFFERING,
            self::MISSIONS,
            self::PROJECTS,
            self::DONATION,
            self::KCA,
            self::PUBLICATION,
            self::LEGACY,
        ];
    }

    public static function isMemberGiving(string $purposeCode): bool
    {
        return in_array($purposeCode, self::codes(), true);
    }

    /**
     * @param  list<string>|array<int, mixed>  $allowed
     */
    public static function allowedBy(string $purposeCode, array $allowed): bool
    {
        $allowed = array_map('strval', $allowed);
        if (in_array($purposeCode, $allowed, true)) {
            return true;
        }

        return in_array(self::LEGACY, $allowed, true) && self::isMemberGiving($purposeCode);
    }
}
