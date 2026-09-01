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

    public const EVENT_PAYMENT = 'event_payment';

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

    public static function label(?string $purposeCode): string
    {
        return match ($purposeCode) {
            self::TITHE => 'Tithe',
            self::OFFERING => 'Offering',
            self::MISSIONS => 'Missions',
            self::PROJECTS => 'Projects',
            self::DONATION => 'Donation',
            self::KCA => 'KCA',
            self::PUBLICATION => 'Publication',
            self::LEGACY => 'Giving',
            self::EVENT_PAYMENT => 'Event payment',
            default => $purposeCode === null || $purposeCode === ''
                ? 'Giving'
                : ucwords(str_replace('_', ' ', $purposeCode)),
        };
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
