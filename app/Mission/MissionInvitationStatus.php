<?php

namespace App\Mission;

enum MissionInvitationStatus: string
{
    case Received = 'received';
    case UnderReview = 'under_review';
    case InformationRequired = 'information_required';
    case Deferred = 'deferred';
    case Declined = 'declined';
    case Approved = 'approved';
    case Planning = 'planning';
    case Confirmed = 'confirmed';
    case Completed = 'completed';

    public function next(): ?self
    {
        return match ($this) {
            self::Received => self::UnderReview,
            self::UnderReview => self::Approved,
            self::Approved => self::Planning,
            self::Planning => self::Confirmed,
            self::Confirmed => self::Completed,
            default => null,
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::InformationRequired, self::Deferred, self::Declined], true);
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this->next() === $target) {
            return true;
        }

        return in_array($target, $this->exceptionTargets(), true);
    }

    /** @return list<self> */
    public function allowedTargets(): array
    {
        $targets = [];
        if ($this->next() !== null) {
            $targets[] = $this->next();
        }

        return array_values(array_unique([...$targets, ...$this->exceptionTargets()], SORT_REGULAR));
    }

    /** @return list<self> */
    private function exceptionTargets(): array
    {
        return match ($this) {
            self::Received => [self::InformationRequired, self::Declined],
            self::UnderReview => [self::InformationRequired, self::Deferred, self::Declined],
            self::InformationRequired => [self::UnderReview, self::Declined],
            self::Deferred => [self::UnderReview, self::Approved, self::Declined],
            default => [],
        };
    }
}
