<?php

namespace App\Church;

enum HomeChurchApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case InformationRequired = 'information_required';
    case InterviewOrientation = 'interview_orientation';
    case Deferred = 'deferred';
    case Approved = 'approved';
    case Active = 'active';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Suspended = 'suspended';
    case Closed = 'closed';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    /** @return list<self> */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Withdrawn, self::Closed],
            self::Submitted => [self::UnderReview, self::Withdrawn, self::Rejected, self::Closed],
            self::UnderReview => [self::InterviewOrientation, self::InformationRequired, self::Deferred, self::Rejected, self::Closed],
            self::InformationRequired => [self::UnderReview, self::Withdrawn, self::Rejected, self::Closed],
            self::Deferred => [self::UnderReview, self::Rejected, self::Closed],
            self::InterviewOrientation => [self::Approved, self::InformationRequired, self::Deferred, self::Rejected, self::Closed],
            self::Approved => [self::Active, self::Closed],
            self::Active => [self::Suspended, self::Closed],
            self::Suspended => [self::Active, self::Closed],
            self::Rejected, self::Withdrawn, self::Closed => [],
        };
    }

    /** @return list<array{status: string, label: string, tone: string}> */
    public function allowedActions(): array
    {
        $labels = [
            self::Submitted->value => ['Submit', 'primary'],
            self::UnderReview->value => ['Start Review', 'primary'],
            self::InformationRequired->value => ['Request Information', 'warning'],
            self::InterviewOrientation->value => ['Schedule Interview', 'primary'],
            self::Deferred->value => ['Defer', 'warning'],
            self::Approved->value => ['Approve', 'primary'],
            self::Active->value => ['Activate', 'primary'],
            self::Rejected->value => ['Reject', 'danger'],
            self::Withdrawn->value => ['Withdraw', 'warning'],
            self::Suspended->value => ['Suspend', 'danger'],
            self::Closed->value => ['Close', 'danger'],
        ];

        $actions = [];
        foreach ($this->allowedTargets() as $target) {
            [$label, $tone] = $labels[$target->value] ?? [$target->value, 'primary'];
            $actions[] = ['status' => $target->value, 'label' => $label, 'tone' => $tone];
        }

        return $actions;
    }

    public function requiresMandatoryNotes(): bool
    {
        return in_array($this, [self::Rejected, self::Deferred, self::Suspended, self::Closed, self::Withdrawn, self::InformationRequired], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Rejected, self::Closed, self::Withdrawn], true);
    }
}
