<?php

namespace App\Press;

enum PressPublicationStatus: string
{
    case Draft = 'draft';
    case Manuscript = 'manuscript';
    case EditorialReview = 'editorial_review';
    case TheologicalReview = 'theological_review';
    case CopyEditing = 'copy_editing';
    case Design = 'design';
    case IsbnAssignment = 'isbn_assignment';
    case PublicationApproval = 'publication_approval';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Distribution = 'distribution';
    case InformationRequired = 'information_required';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case Unpublished = 'unpublished';
    case Archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTargets(), true);
    }

    /** @return list<self> */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Draft => [self::Manuscript, self::Archived],
            self::Manuscript => [
                self::EditorialReview,
                self::InformationRequired,
                self::ChangesRequested,
                self::Rejected,
                self::Archived,
            ],
            self::EditorialReview => [
                self::TheologicalReview,
                self::InformationRequired,
                self::ChangesRequested,
                self::Rejected,
            ],
            self::TheologicalReview => [
                self::CopyEditing,
                self::InformationRequired,
                self::ChangesRequested,
                self::Rejected,
            ],
            self::CopyEditing => [
                self::Design,
                self::InformationRequired,
                self::ChangesRequested,
                self::Rejected,
            ],
            self::Design => [self::IsbnAssignment, self::PublicationApproval],
            self::IsbnAssignment => [self::PublicationApproval],
            self::PublicationApproval => [
                self::Published,
                self::Scheduled,
                self::ChangesRequested,
                self::Rejected,
            ],
            self::Scheduled => [self::Published, self::Unpublished],
            self::Published => [self::Distribution, self::Unpublished, self::Archived],
            self::Distribution => [self::Unpublished, self::Archived],
            self::InformationRequired, self::ChangesRequested => [self::Manuscript, self::Draft],
            self::Rejected => [self::Draft, self::Manuscript],
            self::Unpublished => [self::Published, self::Archived],
            self::Archived => [],
        };
    }

    /** @return list<string> */
    public function allowedTargetValues(): array
    {
        return array_map(fn (self $status): string => $status->value, $this->allowedTargets());
    }

    public function isPubliclyListable(): bool
    {
        return $this === self::Published || $this === self::Distribution;
    }

    public function allowsHardDelete(): bool
    {
        return $this === self::Draft || $this === self::Manuscript;
    }
}
