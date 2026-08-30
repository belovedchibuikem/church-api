<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\AlertOccurrence;
use App\Models\AlertRule;
use App\Models\CommunicationAudience;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationDeliveryAttempt;
use App\Models\CommunicationNotification;
use App\Models\CommunicationTemplate;
use App\Models\DataSubjectRequest;
use App\Models\EventAttendance;
use App\Models\EventFeedback;
use App\Models\EventRegistration;
use App\Models\FileAsset;
use App\Models\GuardianRelationship;
use App\Models\KcaApplication;
use App\Models\KcaAssessmentResult;
use App\Models\KcaAssignment;
use App\Models\KcaAttendance;
use App\Models\KcaCertificate;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaEvidenceReview;
use App\Models\KcaEvidenceSubmission;
use App\Models\KcaLecturerAssignment;
use App\Models\KcaLesson;
use App\Models\KcaMentorAssignment;
use App\Models\KcaModule;
use App\Models\KcaYear;
use App\Models\MinistryEvent;
use App\Models\PaymentDispute;
use App\Models\PaymentIntent;
use App\Models\PaymentReceipt;
use App\Models\PaymentReconciliation;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\PressPublication;
use App\Models\PressPublicationContributor;
use App\Models\PressTranslation;
use App\Models\SafeguardingIncident;
use App\Support\Identity\PersonDisplayName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class ProtectedCatalogRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return match (true) {
            $this->resource instanceof KcaApplication => [
                'id' => $this->public_id,
                'person_id' => $this->person?->public_id,
                'person_name' => PersonDisplayName::of($this->person),
                'status' => $this->status->value,
                'application_data' => $this->application_data,
                'church_name' => data_get($this->application_data, 'church_name')
                    ?? data_get($this->application_data, 'church')
                    ?? data_get($this->application_data, 'home_church'),
                'batch_name' => data_get($this->application_data, 'batch_name')
                    ?? data_get($this->application_data, 'batch')
                    ?? data_get($this->application_data, 'cohort_name')
                    ?? data_get($this->application_data, 'year_name'),
                'received_at' => $this->received_at?->utc()->toIso8601String(),
                'reviewed_at' => $this->reviewed_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof KcaEnrollment => [
                'id' => $this->public_id,
                'application_id' => $this->application?->public_id,
                'person_id' => $this->person?->public_id,
                'person_name' => PersonDisplayName::of($this->person),
                'year_id' => $this->year?->public_id,
                'cohort_id' => $this->cohort?->public_id,
                'starts_on' => $this->starts_on?->toDateString(),
            ],
            $this->resource instanceof KcaAssignment => [
                'id' => $this->public_id,
                'enrollment_id' => $this->enrollment?->public_id,
                'module_id' => $this->module?->public_id,
                'title' => $this->title,
                'state' => $this->state->value,
                'due_at' => $this->due_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof KcaEvidenceSubmission => [
                'id' => $this->public_id,
                'enrollment_id' => $this->enrollment?->public_id,
                'file_asset_id' => $this->fileAsset?->public_id,
                'submitted_by_person_id' => $this->submittedBy?->public_id,
                'submitted_by_name' => PersonDisplayName::of($this->submittedBy),
                'submitted_at' => $this->submitted_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof KcaEvidenceReview => [
                'id' => $this->public_id,
                'evidence_submission_id' => $this->evidenceSubmission?->public_id,
                'reviewer_person_id' => $this->reviewer?->public_id,
                'reviewer_name' => PersonDisplayName::of($this->reviewer),
                'outcome' => $this->outcome->value,
                'reviewed_at' => $this->reviewed_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof KcaAssessmentResult => [
                'id' => $this->public_id,
                'enrollment_id' => $this->enrollment?->public_id,
                'module_id' => $this->module?->public_id,
                'assessment_code' => $this->assessment_code,
                'result_code' => $this->result_code,
                'score' => $this->score,
                'attempt_number' => $this->attempt_number,
                'assessed_at' => $this->assessed_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof KcaCertificate => [
                'id' => $this->public_id,
                'enrollment_id' => $this->enrollment?->public_id,
                'person_id' => $this->person?->public_id,
                'person_name' => PersonDisplayName::of($this->person),
                'completion_on' => $this->completion_on?->toDateString(),
                'issued_at' => $this->issued_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof KcaYear => [
                'id' => $this->public_id,
                'code' => $this->code,
                'name' => $this->name,
                'starts_on' => $this->starts_on?->toDateString(),
                'ends_on' => $this->ends_on?->toDateString(),
            ],
            $this->resource instanceof KcaCohort => [
                'id' => $this->public_id,
                'year_id' => $this->year?->public_id,
                'year_name' => $this->year?->name,
                'code' => $this->code,
                'name' => $this->name,
                'starts_on' => $this->starts_on?->toDateString(),
                'ends_on' => $this->ends_on?->toDateString(),
            ],
            $this->resource instanceof KcaModule => [
                'id' => $this->public_id,
                'code' => $this->code,
                'title' => $this->title,
                'sequence' => $this->sequence,
                'is_active' => $this->is_active,
            ],
            $this->resource instanceof KcaLesson => [
                'id' => $this->public_id,
                'module_id' => $this->module?->public_id,
                'code' => $this->code,
                'title' => $this->title,
                'sequence' => $this->sequence,
            ],
            $this->resource instanceof KcaLecturerAssignment => [
                'id' => $this->public_id,
                'lecturer_person_id' => $this->lecturer?->public_id,
                'lecturer_name' => PersonDisplayName::of($this->lecturer),
                'person_name' => PersonDisplayName::of($this->lecturer),
                'module_id' => $this->module?->public_id,
                'module_title' => $this->module?->title,
                'module_code' => $this->module?->code,
                'cohort_id' => $this->cohort?->public_id,
                'cohort_name' => $this->cohort?->name,
                'starts_at' => $this->starts_at?->utc()->toIso8601String(),
                'ends_at' => $this->ends_at?->utc()->toIso8601String(),
                'status' => $this->ends_at === null || $this->ends_at->isFuture() ? 'Active' : 'Ended',
            ],
            $this->resource instanceof KcaMentorAssignment => [
                'id' => $this->public_id,
                'mentor_person_id' => $this->mentor?->public_id,
                'mentor_name' => PersonDisplayName::of($this->mentor),
                'person_name' => PersonDisplayName::of($this->mentor),
                'enrollment_id' => $this->enrollment?->public_id,
                'student_name' => PersonDisplayName::of($this->enrollment?->person),
                'starts_at' => $this->starts_at?->utc()->toIso8601String(),
                'ends_at' => $this->ends_at?->utc()->toIso8601String(),
                'status' => $this->ends_at === null || $this->ends_at->isFuture() ? 'Active' : 'Ended',
            ],
            $this->resource instanceof KcaAttendance => [
                'id' => $this->public_id,
                'enrollment_id' => $this->enrollment?->public_id,
                'lesson_id' => $this->lesson?->public_id,
                'status' => $this->status->value,
                'session_on' => $this->session_on?->toDateString(),
            ],
            $this->resource instanceof PressPublication => [
                'id' => $this->public_id,
                'title' => $this->title,
                'subtitle' => $this->subtitle,
                'language_code' => $this->language_code,
                'category' => $this->category,
                'format' => $this->format->value,
                'availability' => $this->availability->value,
                'status' => $this->status->value,
                'isbn' => $this->isbn,
                'published_at' => $this->published_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PressPublicationContributor => [
                'id' => $this->public_id,
                'publication_id' => $this->publication?->public_id,
                'person_id' => $this->person?->public_id,
                'person_name' => PersonDisplayName::of($this->person),
                'role' => $this->role->value,
            ],
            $this->resource instanceof PressTranslation => [
                'id' => $this->public_id,
                'publication_id' => $this->publication?->public_id,
                'target_language_code' => $this->target_language_code,
                'translated_title' => $this->translated_title,
                'status' => $this->status->value,
                'status_changed_at' => $this->status_changed_at?->utc()->toIso8601String(),
                'approved_at' => $this->approved_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof MinistryEvent => [
                'id' => $this->public_id,
                'name' => $this->name,
                'category_code' => $this->category_code,
                'location_id' => $this->location?->public_id,
                'starts_at' => $this->starts_at?->utc()->toIso8601String(),
                'ends_at' => $this->ends_at?->utc()->toIso8601String(),
                'published_at' => $this->published_at?->utc()->toIso8601String(),
                'fee_amount_minor' => $this->fee_amount_minor,
                'fee_currency' => $this->fee_currency,
            ],
            $this->resource instanceof EventRegistration => [
                'id' => $this->public_id,
                'event_id' => $this->event?->public_id,
                'person_id' => $this->person?->public_id,
                'person_name' => PersonDisplayName::of($this->person),
                'status' => $this->status->value,
                'registered_at' => $this->registered_at?->utc()->toIso8601String(),
                'confirmed_at' => $this->confirmed_at?->utc()->toIso8601String(),
                'payment_required' => ($this->event?->fee_amount_minor ?? 0) > 0,
                'fee_amount_minor' => $this->event?->fee_amount_minor,
                'fee_currency' => $this->event?->fee_currency,
                'event_name' => $this->event?->name,
                'ticket_code' => $this->ticket_code,
                'qr_payload' => $this->ticket_code ? 'fhc:ticket:'.$this->ticket_code : null,
                'starts_at' => $this->event?->starts_at?->utc()->toIso8601String(),
                'ends_at' => $this->event?->ends_at?->utc()->toIso8601String(),
                'location' => $this->event !== null && $this->event->relationLoaded('location')
                    ? ($this->event->location === null ? null : [
                        'id' => $this->event->location->public_id,
                        'name' => $this->event->location->name,
                        'locality' => $this->event->location->locality,
                    ])
                    : null,
            ],
            $this->resource instanceof EventAttendance => [
                'id' => $this->public_id,
                'registration_id' => $this->registration?->public_id,
                'source_code' => $this->source_code,
                'attended_at' => $this->attended_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof EventFeedback => [
                'id' => $this->public_id,
                'registration_id' => $this->registration?->public_id,
                'rating' => $this->rating,
                'submitted_at' => $this->submitted_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PaymentIntent => [
                'id' => $this->public_id,
                'payer_person_id' => $this->payer?->public_id,
                'payer_name' => PersonDisplayName::of($this->payer),
                'purpose_code' => $this->purpose_code,
                'amount_minor' => $this->amount_minor,
                'currency' => $this->currency,
                'status' => $this->status->value,
                'succeeded_at' => $this->succeeded_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PaymentTransaction => [
                'id' => $this->public_id,
                'payment_intent_id' => $this->intent?->public_id,
                'provider_code' => $this->provider_code,
                'amount_minor' => $this->amount_minor,
                'currency' => $this->currency,
                'occurred_at' => $this->occurred_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PaymentReconciliation => [
                'id' => $this->public_id,
                'payment_transaction_id' => $this->transaction?->public_id,
                'status' => $this->status->value,
                'reason_code' => $this->reason_code,
                'reconciled_at' => $this->reconciled_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PaymentReceipt => [
                'id' => $this->public_id,
                'payment_transaction_id' => $this->transaction?->public_id,
                'receipt_number' => $this->receipt_number,
                'issued_at' => $this->issued_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PaymentRefund => [
                'id' => $this->public_id,
                'payment_transaction_id' => $this->transaction?->public_id,
                'amount_minor' => $this->amount_minor,
                'currency' => $this->currency,
                'reason_code' => $this->reason_code,
                'status' => $this->status->value,
                'requested_at' => $this->requested_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PaymentDispute => [
                'id' => $this->public_id,
                'payment_transaction_id' => $this->transaction?->public_id,
                'status' => $this->status->value,
                'reason_code' => $this->reason_code,
                'amount_minor' => $this->amount_minor,
                'occurred_at' => $this->occurred_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof CommunicationTemplate => [
                'id' => $this->public_id,
                'code' => $this->code,
                'channel' => $this->channel->value,
                'locale' => $this->locale,
                'subject' => $this->subject,
            ],
            $this->resource instanceof CommunicationAudience => [
                'id' => $this->public_id,
                'code' => $this->code,
                'name' => $this->name,
            ],
            $this->resource instanceof CommunicationBroadcast => [
                'id' => $this->public_id,
                'template_id' => $this->template?->public_id,
                'audience_id' => $this->audience?->public_id,
                'kind' => $this->kind->value,
                'channel' => $this->channel->value,
                'purpose' => $this->purpose,
                'status' => $this->status->value,
                'scheduled_at' => $this->scheduled_at?->utc()->toIso8601String(),
                'prepared_at' => $this->prepared_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof CommunicationDeliveryAttempt => [
                'id' => $this->public_id,
                'recipient_id' => $this->recipient?->public_id,
                'channel' => $this->channel->value,
                'status' => $this->status->value,
                'result_code' => $this->result_code,
                'attempted_at' => $this->attempted_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof CommunicationNotification => [
                'id' => $this->public_id,
                'person_id' => $this->person?->public_id,
                'person_name' => PersonDisplayName::of($this->person),
                'user_id' => $this->user?->public_id,
                'read_at' => $this->read_at?->utc()->toIso8601String(),
                'created_at' => $this->created_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof AlertRule => [
                'id' => $this->public_id,
                'code' => $this->code,
                'title' => $this->title,
                'condition_type' => $this->condition_type,
                'severity' => $this->severity->value,
                'scope_type' => $this->scope_type,
                'scope_key' => $this->scope_key,
                'is_active' => $this->is_active,
            ],
            $this->resource instanceof AlertOccurrence => [
                'id' => $this->public_id,
                'alert_rule_id' => $this->rule?->public_id,
                'condition_reference_type' => $this->condition_reference_type,
                'scope_type' => $this->scope_type,
                'scope_key' => $this->scope_key,
                'status' => $this->status->value,
                'opened_at' => $this->opened_at?->utc()->toIso8601String(),
                'acknowledged_at' => $this->acknowledged_at?->utc()->toIso8601String(),
                'resolved_at' => $this->resolved_at?->utc()->toIso8601String(),
                'resolution_reason_code' => $this->resolution_reason_code,
            ],
            $this->resource instanceof DataSubjectRequest => [
                'id' => $this->public_id,
                'person_id' => $this->person?->public_id,
                'person_name' => PersonDisplayName::of($this->person),
                'request_type' => $this->request_type->value,
                'status' => $this->status->value,
                'requested_at' => $this->requested_at?->utc()->toIso8601String(),
                'reviewed_at' => $this->reviewed_at?->utc()->toIso8601String(),
                'completed_at' => $this->completed_at?->utc()->toIso8601String(),
                'export_expires_at' => $this->export_expires_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof FileAsset => [
                'id' => $this->public_id,
                'owner_person_id' => $this->owner?->public_id,
                'owner_name' => PersonDisplayName::of($this->owner),
                'purpose' => $this->purpose,
                'classification' => $this->classification->value,
                'storage_provider' => $this->storage_provider->value,
                'disk_name' => $this->disk_name,
                'detected_mime_type' => $this->detected_mime_type,
                'byte_size' => $this->byte_size,
                'status' => $this->status->value,
                'malware_scan_status' => $this->malware_scan_status->value,
                'available_at' => $this->available_at?->utc()->toIso8601String(),
                'created_at' => $this->created_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof SafeguardingIncident => [
                'id' => $this->public_id,
                'concern_type' => $this->concern_type,
                'severity' => $this->severity->value,
                'status' => $this->status->value,
                'subject_person_id' => $this->subject?->public_id,
                'subject_name' => PersonDisplayName::of($this->subject),
                'occurred_at' => $this->occurred_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof GuardianRelationship => [
                'id' => $this->public_id,
                'guardian_person_id' => $this->guardian?->public_id,
                'guardian_name' => PersonDisplayName::of($this->guardian),
                'child_person_id' => $this->child?->public_id,
                'child_name' => PersonDisplayName::of($this->child),
                'relationship_type' => $this->relationship_type,
                'status' => $this->status->value,
            ],
            default => throw new LogicException('Unsupported protected catalog resource.'),
        };
    }
}
