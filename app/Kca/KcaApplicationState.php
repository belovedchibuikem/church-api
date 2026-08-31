<?php

namespace App\Kca;

enum KcaApplicationState: string
{
    case Draft = 'draft';
    case Received = 'received';
    case InformationRequired = 'information_required';
    case Interview = 'interview';
    case Reviewed = 'reviewed';
    case Accepted = 'accepted';
    case ProvisionallyAccepted = 'provisionally_accepted';
    case Deferred = 'deferred';
    case NotAccepted = 'not_accepted';
    case Withdrawn = 'withdrawn';
    case Suspended = 'suspended';
    case Revoked = 'revoked';

    public function isAdmissionOutcome(): bool
    {
        return match ($this) {
            self::Accepted,
            self::ProvisionallyAccepted,
            self::Deferred,
            self::NotAccepted,
            self::Withdrawn,
            self::Suspended,
            self::Revoked => true,
            default => false,
        };
    }

    public function permitsEnrollment(): bool
    {
        return $this === self::Accepted || $this === self::ProvisionallyAccepted;
    }

    public function allowsApplicantEdit(): bool
    {
        return $this === self::Draft || $this === self::InformationRequired;
    }

    public function requiresDecisionReason(): bool
    {
        return match ($this) {
            self::Deferred, self::NotAccepted, self::Suspended, self::Revoked => true,
            default => false,
        };
    }

    public function destination(): string
    {
        return match ($this) {
            self::Draft => 'resume_application',
            self::Received, self::Reviewed => 'admission_progress',
            self::InformationRequired => 'information_required',
            self::Interview => 'orientation',
            self::ProvisionallyAccepted => 'provisional_offer',
            self::Accepted => 'admission_letter',
            self::Deferred => 'deferred',
            self::NotAccepted => 'not_admitted',
            self::Withdrawn => 'withdrawn',
            self::Suspended, self::Revoked => 'restricted',
        };
    }

    public function publicLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Received => 'Submitted',
            self::InformationRequired => 'Information required',
            self::Interview => 'Interview / orientation',
            self::Reviewed => 'Under review',
            self::Accepted => 'Admitted',
            self::ProvisionallyAccepted => 'Provisionally accepted',
            self::Deferred => 'Deferred',
            self::NotAccepted => 'Not admitted',
            self::Withdrawn => 'Withdrawn',
            self::Suspended => 'Suspended',
            self::Revoked => 'Revoked',
        };
    }
}
