<?php

namespace App\Http\Controllers\Api\V1\Admin\Concerns;

use App\Exceptions\AlertExecutionDeniedException;
use App\Exceptions\AlertInvalidStateException;
use App\Exceptions\CommunicationAudienceRuleException;
use App\Exceptions\CommunicationConsentDeniedException;
use App\Exceptions\CommunicationIdempotencyConflictException;
use App\Exceptions\CommunicationInvalidStateException;
use App\Exceptions\DataExportExecutionDeniedException;
use App\Exceptions\DataExportInvalidStateException;
use App\Exceptions\FileAssetIdempotencyConflictException;
use App\Exceptions\FileAssetUnavailableException;
use App\Exceptions\FileAssetValidationException;
use App\Exceptions\KcaCertificateImmutableException;
use App\Exceptions\KcaCertificationNotEligibleException;
use App\Exceptions\KcaEvidenceOwnershipException;
use App\Exceptions\KcaEvidenceUnavailableException;
use App\Exceptions\KcaIdempotencyConflictException;
use App\Exceptions\KcaInvalidTransitionException;
use App\Exceptions\KcaMentorAssignmentException;
use App\Exceptions\MembershipTransferRequiredException;
use App\Exceptions\MissionAssignmentException;
use App\Exceptions\MissionIdempotencyConflictException;
use App\Exceptions\MissionInvalidTransitionException;
use App\Exceptions\MissionJourneyStateException;
use App\Exceptions\MissionSoulAlreadyLinkedException;
use App\Exceptions\PaymentGovernanceDeniedException;
use App\Exceptions\PaymentReconciliationMismatchException;
use App\Exceptions\PaymentVerificationException;
use App\Exceptions\PressTransitionImmutableException;
use DomainException;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

trait ExecutesDomainMutations
{
    private function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (
            InvalidArgumentException|
            LogicException|
            DomainException|
            MembershipTransferRequiredException|
            KcaInvalidTransitionException|
            KcaIdempotencyConflictException|
            KcaEvidenceOwnershipException|
            KcaEvidenceUnavailableException|
            KcaMentorAssignmentException|
            KcaCertificationNotEligibleException|
            KcaCertificateImmutableException|
            PressTransitionImmutableException|
            PaymentGovernanceDeniedException|
            PaymentVerificationException|
            PaymentReconciliationMismatchException|
            CommunicationInvalidStateException|
            CommunicationIdempotencyConflictException|
            CommunicationConsentDeniedException|
            CommunicationAudienceRuleException|
            AlertInvalidStateException|
            AlertExecutionDeniedException|
            DataExportInvalidStateException|
            DataExportExecutionDeniedException|
            FileAssetValidationException|
            FileAssetIdempotencyConflictException|
            FileAssetUnavailableException|
            MissionInvalidTransitionException|
            MissionIdempotencyConflictException|
            MissionAssignmentException|
            MissionJourneyStateException|
            MissionSoulAlreadyLinkedException $exception
        ) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
    }
}
