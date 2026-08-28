<?php

namespace App\Press;

enum PressPublicationStatus: string
{
    case Manuscript = 'manuscript';
    case EditorialReview = 'editorial_review';
    case TheologicalReview = 'theological_review';
    case CopyEditing = 'copy_editing';
    case Design = 'design';
    case IsbnAssignment = 'isbn_assignment';
    case PublicationApproval = 'publication_approval';
    case Published = 'published';
    case Distribution = 'distribution';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Manuscript => $target === self::EditorialReview,
            self::EditorialReview => $target === self::TheologicalReview,
            self::TheologicalReview => $target === self::CopyEditing,
            self::CopyEditing => $target === self::Design,
            self::Design => $target === self::IsbnAssignment,
            self::IsbnAssignment => $target === self::PublicationApproval,
            self::PublicationApproval => $target === self::Published,
            self::Published => $target === self::Distribution,
            self::Distribution => false,
        };
    }
}
