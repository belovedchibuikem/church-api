<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\AlertOccurrence;
use App\Models\AlertRule;
use App\Models\ChildProfile;
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
use App\Models\KcaChapter;
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
use App\Models\KcaOrientationSession;
use App\Models\KcaModulePrerequisite;
use App\Models\KcaYear;
use App\Models\MinistryEvent;
use App\Models\PaymentDispute;
use App\Models\PaymentIntent;
use App\Models\PaymentReceipt;
use App\Models\PaymentReconciliation;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\PressAuthor;
use App\Models\PressPublication;
use App\Models\PressPublicationAsset;
use App\Models\PressPublicationContributor;
use App\Models\PressPublicationReview;
use App\Models\PressTranslation;
use App\Models\SafeguardingIncident;
use App\Press\PressContributorRole;
use App\Support\Communication\CommunicationCopy;
use App\Support\Identity\PersonDisplayName;
use App\Support\Kca\ResolveKcaApplicationChurchName;
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
                'church_name' => app(ResolveKcaApplicationChurchName::class)->fromApplicationData($this->application_data),
                'batch_name' => app(ResolveKcaApplicationChurchName::class)->batchLabel($this->resource),
                'admission_letter_id' => $this->admissionLetter?->public_id,
                'admission_letter_issued_at' => $this->admissionLetter?->issued_at?->utc()->toIso8601String(),
                'received_at' => $this->received_at?->utc()->toIso8601String(),
                'reviewed_at' => $this->reviewed_at?->utc()->toIso8601String(),
                'orientation_completed_at' => $this->orientation_completed_at?->utc()->toIso8601String(),
                'orientation_progress' => $this->orientation_progress ?? [],
                'recommendation_id' => $this->leadershipRecommendation?->public_id,
                'recommendation_status' => $this->leadershipRecommendation?->status,
            ],
            $this->resource instanceof KcaEnrollment => [
                'id' => $this->public_id,
                'application_id' => $this->application?->public_id,
                'person_id' => $this->person?->public_id,
                'person_name' => PersonDisplayName::of($this->person),
                'registration_number' => $this->registration_number,
                'year_id' => $this->year?->public_id,
                'year_name' => $this->year?->name,
                'cohort_id' => $this->cohort?->public_id,
                'cohort_name' => $this->cohort?->name,
                'mentor_name' => PersonDisplayName::of($this->mentorAssignments->first()?->mentor),
                'status' => 'Active',
                'starts_on' => $this->starts_on?->toDateString(),
            ],
            $this->resource instanceof KcaAssignment => [
                'id' => $this->public_id,
                'enrollment_id' => $this->enrollment?->public_id,
                'student_name' => PersonDisplayName::of($this->enrollment?->person),
                'person_name' => PersonDisplayName::of($this->enrollment?->person),
                'module_id' => $this->module?->public_id,
                'module_title' => $this->module?->title,
                'title' => $this->title,
                'assignment_kind' => $this->assignment_kind ?? 'standard',
                'kind' => $this->assignment_kind ?? 'standard',
                'soul_tree_spec' => $this->soul_tree_spec,
                'state' => $this->state->value,
                'status' => $this->state->value,
                'due_at' => $this->due_at?->utc()->toIso8601String(),
                'assigned_at' => $this->assigned_at?->utc()->toIso8601String(),
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
                'person_name' => PersonDisplayName::of($this->enrollment?->person),
                'module_id' => $this->module?->public_id,
                'module_title' => $this->module?->title,
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
                'timezone' => $this->timezone,
                'students_count' => $this->enrollments_count ?? $this->enrollments()->count(),
                'status' => $this->lifecycleStatus(),
            ],
            $this->resource instanceof KcaOrientationSession => [
                'id' => $this->public_id,
                'name' => $this->name,
                'cohort_id' => $this->cohort?->public_id,
                'cohort_name' => $this->cohort?->name,
                'location_id' => $this->location?->public_id,
                'location_name' => $this->location?->name,
                'venue' => $this->venueDisplay(),
                'venue_label' => $this->venue_label,
                'starts_at' => $this->starts_at?->utc()->toIso8601String(),
                'ends_at' => $this->ends_at?->utc()->toIso8601String(),
                'capacity' => $this->capacity,
                'notes' => $this->notes,
                'published_at' => $this->published_at?->utc()->toIso8601String(),
                'students_count' => $this->cohort?->enrollments_count,
                'status' => $this->lifecycleStatus()->value,
                'updated_at' => $this->updated_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof KcaModule => [
                'id' => $this->public_id,
                'code' => $this->code,
                'title' => $this->title,
                'sequence' => $this->sequence,
                'duration_days' => $this->duration_days,
                'is_active' => $this->is_active,
                'lessons_count' => $this->lessons_count ?? $this->lessons()->count(),
                'published_at' => $this->published_at?->utc()->toIso8601String(),
                'status' => $this->published_at ? 'published' : 'draft',
            ],
            $this->resource instanceof KcaLesson => [
                'id' => $this->public_id,
                'module_id' => $this->module?->public_id,
                'module_title' => $this->module?->title,
                'code' => $this->code,
                'title' => $this->title,
                'summary' => $this->summary,
                'body' => $this->body,
                'content_url' => $this->content_url,
                'estimated_minutes' => $this->estimated_minutes,
                'sequence' => $this->sequence,
                'day_index' => $this->day_index,
                'lesson_type' => $this->lesson_type,
                'chapters_count' => $this->relationLoaded('chapters') ? $this->chapters->count() : null,
                'status' => 'Active',
            ],
            $this->resource instanceof KcaChapter => [
                'id' => $this->public_id,
                'lesson_id' => $this->lesson?->public_id,
                'lesson_title' => $this->lesson?->title,
                'code' => $this->code,
                'title' => $this->title,
                'summary' => $this->summary,
                'body' => $this->body,
                'content_url' => $this->content_url,
                'estimated_minutes' => $this->estimated_minutes,
                'sequence' => $this->sequence,
                'status' => 'Active',
            ],
            $this->resource instanceof KcaModulePrerequisite => [
                'id' => $this->public_id,
                'module_id' => $this->module?->public_id,
                'module_title' => $this->module?->title,
                'prerequisite_module_id' => $this->prerequisiteModule?->public_id,
                'prerequisite_module_title' => $this->prerequisiteModule?->title,
                'requirement' => $this->requirement->value,
                'status' => 'Active',
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
                'person_name' => PersonDisplayName::of($this->enrollment?->person),
                'registration_number' => $this->enrollment?->registration_number,
                'lesson_id' => $this->lesson?->public_id,
                'lesson_title' => $this->lesson?->title,
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
                'publication_type' => $this->publicationType()->value,
                'type' => $this->publicationType()->value,
                'availability' => $this->availability->value,
                'visibility' => $this->visibilityEnum()->value,
                'status' => $this->status->value,
                'allowed_transitions' => $this->status->allowedTargetValues(),
                'isbn' => $this->isbn,
                'publisher_name' => $this->publisher_name,
                'author_name' => PersonDisplayName::of(
                    $this->relationLoaded('contributors')
                        ? $this->contributors->firstWhere('role', PressContributorRole::Author)?->person
                            ?? $this->contributors->first()?->person
                        : null,
                ) ?: '—',
                'published_at' => $this->published_at?->utc()->toIso8601String(),
                'created_at' => $this->created_at?->utc()->toIso8601String(),
                'updated_at' => $this->updated_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PressPublicationAsset => [
                'id' => $this->public_id,
                'publication_id' => $this->publication?->public_id,
                'title' => $this->publication?->title,
                'name' => $this->label ?? $this->asset_format->value,
                'type' => $this->asset_format->value,
                'status' => $this->processing_status->value,
                'updated_at' => $this->updated_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PressPublicationReview => [
                'id' => $this->public_id,
                'publication_id' => $this->publication?->public_id,
                'title' => $this->publication?->title,
                'name' => $this->publication?->title,
                'stage' => $this->stage->value,
                'status' => $this->decision->value,
                'person_name' => PersonDisplayName::of($this->reviewer),
                'updated_at' => $this->decided_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PressAuthor => [
                'id' => $this->public_id,
                'person_id' => $this->person?->public_id,
                'name' => $this->display_name,
                'person_name' => $this->display_name,
                'status' => $this->status,
                'updated_at' => $this->updated_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PressPublicationContributor => [
                'id' => $this->public_id,
                'publication_id' => $this->publication?->public_id,
                'title' => $this->publication?->title,
                'person_id' => $this->person?->public_id,
                'person_name' => PersonDisplayName::of($this->person),
                'name' => PersonDisplayName::of($this->person),
                'role' => $this->role->value,
                'type' => $this->role->value,
                'status' => 'active',
                'updated_at' => $this->updated_at?->utc()->toIso8601String(),
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
                'location_name' => $this->location?->name,
                'starts_at' => $this->starts_at?->utc()->toIso8601String(),
                'ends_at' => $this->ends_at?->utc()->toIso8601String(),
                'registration_opens_at' => $this->registration_opens_at?->utc()->toIso8601String(),
                'registration_closes_at' => $this->registration_closes_at?->utc()->toIso8601String(),
                'published_at' => $this->published_at?->utc()->toIso8601String(),
                'fee_amount_minor' => $this->fee_amount_minor,
                'fee_currency' => $this->fee_currency,
                'capacity' => $this->capacity,
                'status' => \App\Events\MinistryEventLifecycleStatus::forEvent($this->resource)->value,
                'updated_at' => $this->updated_at?->utc()->toIso8601String(),
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
                'donor_name' => PersonDisplayName::of($this->payer),
                'purpose_code' => $this->purpose_code,
                'category' => $this->purpose_code,
                'amount_minor' => $this->amount_minor,
                'amount' => $this->formatMoneyMinor((int) $this->amount_minor, (string) $this->currency),
                'currency' => $this->currency,
                'status' => $this->status->value,
                'proof_file_asset_id' => $this->proofFileAsset?->public_id,
                'succeeded_at' => $this->succeeded_at?->utc()->toIso8601String(),
                'created_at' => $this->created_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PaymentTransaction => [
                'id' => $this->public_id,
                'payment_intent_id' => $this->intent?->public_id,
                'provider_code' => $this->provider_code,
                'channel' => $this->provider_code,
                'amount_minor' => $this->amount_minor,
                'amount' => $this->formatMoneyMinor((int) $this->amount_minor, (string) $this->currency),
                'currency' => $this->currency,
                'purpose_code' => $this->intent?->purpose_code,
                'category' => $this->intent?->purpose_code,
                'payer_name' => PersonDisplayName::of($this->intent?->payer),
                'donor_name' => PersonDisplayName::of($this->intent?->payer),
                'status' => $this->intent?->status?->value ?? 'recorded',
                'reconciliation_status' => $this->reconciliation?->status?->value,
                'receipt_number' => $this->receipt?->receipt_number,
                'occurred_at' => $this->occurred_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PaymentReconciliation => [
                'id' => $this->public_id,
                'payment_transaction_id' => $this->transaction?->public_id,
                'channel' => $this->transaction?->provider_code,
                'amount' => $this->formatMoneyMinor(
                    (int) ($this->transaction?->amount_minor ?? 0),
                    (string) ($this->transaction?->currency ?? 'NGN'),
                ),
                'donor_name' => PersonDisplayName::of($this->transaction?->intent?->payer),
                'category' => $this->transaction?->intent?->purpose_code,
                'status' => $this->status->value,
                'reason_code' => $this->reason_code,
                'reason' => $this->reason_code,
                'reconciled_at' => $this->reconciled_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PaymentReceipt => [
                'id' => $this->public_id,
                'payment_transaction_id' => $this->transaction?->public_id,
                'receipt_number' => $this->receipt_number,
                'donor_name' => PersonDisplayName::of($this->transaction?->intent?->payer),
                'category' => $this->transaction?->intent?->purpose_code,
                'amount' => $this->formatMoneyMinor(
                    (int) ($this->transaction?->amount_minor ?? 0),
                    (string) ($this->transaction?->currency ?? 'NGN'),
                ),
                'status' => 'issued',
                'issued_at' => $this->issued_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PaymentRefund => [
                'id' => $this->public_id,
                'payment_transaction_id' => $this->transaction?->public_id,
                'donor_name' => PersonDisplayName::of($this->transaction?->intent?->payer),
                'reason' => $this->reason_code,
                'reason_code' => $this->reason_code,
                'amount_minor' => $this->amount_minor,
                'amount' => $this->formatMoneyMinor((int) $this->amount_minor, (string) $this->currency),
                'currency' => $this->currency,
                'status' => $this->status->value,
                'requested_at' => $this->requested_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof PaymentDispute => [
                'id' => $this->public_id,
                'payment_transaction_id' => $this->transaction?->public_id,
                'donor_name' => PersonDisplayName::of($this->transaction?->intent?->payer),
                'reason' => $this->reason_code,
                'reason_code' => $this->reason_code,
                'amount_minor' => $this->amount_minor,
                'amount' => $this->formatMoneyMinor(
                    (int) ($this->amount_minor ?? $this->transaction?->amount_minor ?? 0),
                    (string) ($this->transaction?->currency ?? 'NGN'),
                ),
                'status' => $this->status->value,
                'occurred_at' => $this->occurred_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof CommunicationTemplate => [
                'id' => $this->public_id,
                'code' => $this->code,
                'channel' => $this->channel->value,
                'locale' => $this->locale,
                'subject' => $this->subject,
                'title' => $this->subject,
                'message' => $this->subject,
                'created_at' => $this->created_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof CommunicationAudience => [
                'id' => $this->public_id,
                'code' => $this->code,
                'name' => $this->name,
                'title' => $this->name,
                'status' => 'active',
                'created_at' => $this->created_at?->utc()->toIso8601String(),
                'updated_at' => $this->updated_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof CommunicationBroadcast => [
                'id' => $this->public_id,
                'template_id' => $this->template?->public_id,
                'audience_id' => $this->audience?->public_id,
                'title' => CommunicationCopy::campaignTitle(
                    $this->template?->subject,
                    (string) $this->purpose,
                ),
                'message' => CommunicationCopy::campaignTitle(
                    $this->template?->subject,
                    (string) $this->purpose,
                ),
                'subject' => $this->template?->subject,
                'kind' => $this->kind->value,
                'channel' => $this->channel->value,
                'purpose' => $this->purpose,
                'purpose_label' => CommunicationCopy::purposeLabel((string) $this->purpose),
                'audience' => $this->audience?->name ?? $this->audience?->code,
                'status' => $this->status->value,
                'scheduled_at' => $this->scheduled_at?->utc()->toIso8601String(),
                'prepared_at' => $this->prepared_at?->utc()->toIso8601String(),
                'created_at' => $this->created_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof CommunicationDeliveryAttempt => [
                'id' => $this->public_id,
                'recipient_id' => $this->recipient?->public_id,
                'title' => CommunicationCopy::campaignTitle(
                    $this->recipient?->broadcast?->template?->subject,
                    (string) ($this->recipient?->broadcast?->purpose ?? ''),
                ),
                'message' => CommunicationCopy::campaignTitle(
                    $this->recipient?->broadcast?->template?->subject,
                    (string) ($this->recipient?->broadcast?->purpose ?? $this->result_code ?? ''),
                ),
                'audience' => $this->recipient?->broadcast?->audience?->name
                    ?? $this->recipient?->broadcast?->audience?->code,
                'channel' => $this->channel->value,
                'status' => $this->status->value,
                'result_code' => $this->result_code,
                'attempted_at' => $this->attempted_at?->utc()->toIso8601String(),
                'created_at' => $this->created_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof CommunicationNotification => [
                'id' => $this->public_id,
                'person_id' => $this->person?->public_id,
                'person_name' => PersonDisplayName::of($this->person),
                'user_id' => $this->user?->public_id,
                'title' => PersonDisplayName::of($this->person) ?: 'Notification',
                'name' => PersonDisplayName::of($this->person) ?: 'Notification',
                'message' => PersonDisplayName::of($this->person) ?: 'In-app notification',
                'detail' => $this->read_at ? 'read' : 'unread',
                'status' => $this->read_at ? 'read' : 'unread',
                'read_at' => $this->read_at?->utc()->toIso8601String(),
                'created_at' => $this->created_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof AlertRule => [
                'id' => $this->public_id,
                'code' => $this->code,
                'title' => $this->title,
                'name' => $this->title,
                'condition_type' => $this->condition_type,
                'type' => $this->condition_type,
                'severity' => $this->severity->value,
                'scope_type' => $this->scope_type,
                'scope_key' => $this->scope_key,
                'is_active' => $this->is_active,
                'status' => $this->is_active ? 'active' : 'inactive',
                'created_at' => $this->created_at?->utc()->toIso8601String(),
                'updated_at' => $this->updated_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof AlertOccurrence => [
                'id' => $this->public_id,
                'alert_rule_id' => $this->rule?->public_id,
                'title' => $this->rule?->title ?? $this->condition_reference_type,
                'name' => $this->rule?->title ?? $this->condition_reference_type,
                'message' => $this->rule?->title ?? $this->condition_reference_type,
                'condition_reference_type' => $this->condition_reference_type,
                'type' => $this->condition_reference_type,
                'detail' => $this->status->value,
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
                'reference_code' => $this->reference_code,
                'name' => PersonDisplayName::of($this->subject) ?: $this->concern_type,
                'concern_type' => $this->concern_type,
                'type' => $this->concern_type,
                'severity' => $this->severity->value,
                'status' => $this->status->value,
                'subject_person_id' => $this->subject?->public_id,
                'subject_name' => PersonDisplayName::of($this->subject),
                'occurred_at' => $this->occurred_at?->utc()->toIso8601String(),
                'updated_at' => $this->updated_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof ChildProfile => [
                'id' => $this->public_id,
                'person_id' => $this->person?->public_id,
                'name' => PersonDisplayName::of($this->person),
                'person_name' => PersonDisplayName::of($this->person),
                'minor_status' => $this->minor_status->value,
                'status' => $this->minor_status->value,
                'direct_communication_restricted' => $this->direct_communication_restricted,
                'media_use_restricted' => $this->media_use_restricted,
                'type' => implode(', ', array_filter([
                    $this->direct_communication_restricted ? 'communication restricted' : null,
                    $this->media_use_restricted ? 'media restricted' : null,
                ])) ?: 'open',
                'updated_at' => $this->updated_at?->utc()->toIso8601String(),
            ],
            $this->resource instanceof GuardianRelationship => [
                'id' => $this->public_id,
                'name' => PersonDisplayName::of($this->child)
                    ?: PersonDisplayName::of($this->guardian)
                    ?: $this->relationship_type,
                'guardian_person_id' => $this->guardian?->public_id,
                'guardian_name' => PersonDisplayName::of($this->guardian),
                'child_person_id' => $this->child?->public_id,
                'child_name' => PersonDisplayName::of($this->child),
                'relationship_type' => $this->relationship_type,
                'type' => $this->relationship_type,
                'status' => $this->status->value,
                'created_at' => $this->created_at?->utc()->toIso8601String(),
                'updated_at' => $this->updated_at?->utc()->toIso8601String(),
            ],
            default => throw new LogicException('Unsupported protected catalog resource.'),
        };
    }

    private function formatMoneyMinor(int $amountMinor, string $currency): string
    {
        $major = number_format($amountMinor / 100, 2);
        $code = strtoupper($currency !== '' ? $currency : 'NGN');

        return match ($code) {
            'NGN' => '₦'.$major,
            'USD' => '$'.$major,
            'GBP' => '£'.$major,
            'EUR' => '€'.$major,
            default => $code.' '.$major,
        };
    }
}
