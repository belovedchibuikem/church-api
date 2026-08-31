<?php

namespace App\Mission;

enum CrusadeStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InformationRequired = 'information_required';
    case Approved = 'approved';
    case Planning = 'planning';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Reported = 'reported';
    case Archived = 'archived';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case Closed = 'closed';

    public function requiresReason(): bool
    {
        return in_array($this, [
            self::InformationRequired,
            self::Postponed,
            self::Cancelled,
            self::Closed,
            self::Archived,
        ], true);
    }

    /** @return list<self> */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Approved, self::InformationRequired, self::Cancelled],
            self::InformationRequired => [self::Submitted, self::Cancelled],
            self::Approved => [self::Planning, self::Cancelled],
            self::Planning => [self::Scheduled, self::Postponed, self::Cancelled],
            self::Scheduled => [self::Active, self::Postponed, self::Cancelled],
            self::Active => [self::Completed, self::Postponed, self::Cancelled],
            self::Completed => [self::Reported, self::Closed],
            self::Reported => [self::Archived, self::Closed],
            self::Postponed => [self::Planning, self::Scheduled, self::Cancelled],
            self::Cancelled, self::Closed, self::Archived => [],
        };
    }

    public function isPubliclyVisible(): bool
    {
        return in_array($this, [self::Scheduled, self::Active, self::Completed, self::Reported], true);
    }
}
