<?php

namespace App\Press;

enum PressTranslationStatus: string
{
    case MachineGenerated = 'machine_generated';
    case UnderReview = 'under_review';
    case Reviewed = 'reviewed';
    case Approved = 'approved';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::MachineGenerated => $target === self::UnderReview,
            self::UnderReview => $target === self::Reviewed,
            self::Reviewed => $target === self::Approved,
            self::Approved => false,
        };
    }
}
