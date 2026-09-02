<?php

namespace App\Services\Admin;

use App\Models\AlertOccurrence;
use App\Models\AlertRule;
use App\Models\ChildProfile;
use App\Models\CommunicationAudience;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationDeliveryAttempt;
use App\Models\CommunicationNotification;
use App\Models\CommunicationTemplate;
use App\Models\DataSubjectRequest;
use App\Models\EventRegistration;
use App\Models\FileAsset;
use App\Models\GuardianRelationship;
use App\Models\KcaApplication;
use App\Models\KcaAssessmentResult;
use App\Models\KcaAttendance;
use App\Models\KcaCertificate;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaEvidenceSubmission;
use App\Models\KcaLecturerAssignment;
use App\Models\KcaLesson;
use App\Models\KcaChapter;
use App\Models\KcaMentorAssignment;
use App\Models\KcaModule;
use App\Models\KcaModulePrerequisite;
use App\Models\KcaOrientationSession;
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
use App\Support\Identity\PersonDisplayName;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProtectedDomainRegistry
{
    /**
     * Allowlisted remaining-domain catalog resources.
     *
     * @return array<string, array{
     *     permission: string,
     *     path: string,
     *     operation_id: string,
     *     model: class-string<Model>,
     *     with?: array<int, string>,
     *     order_column: string,
     *     order_direction?: 'asc'|'desc',
     *     search_column?: string,
     *     search_columns?: array<int, string>,
     *     search_profile_relation?: string,
     *     status_column?: string,
     *     status_relation?: string,
     *     purpose_column?: string,
     *     purpose_relation?: string
     * }>
     */
    public function definitions(): array
    {
        return [
            'kca.applications' => [
                'permission' => 'kca.applications.view',
                'path' => 'kca/applications',
                'operation_id' => 'listAdminCatalogKcaApplications',
                'model' => KcaApplication::class,
                'with' => [...PersonDisplayName::eager(), 'leadershipRecommendation', 'enrollment.cohort:id,public_id,name', 'admissionLetter:id,public_id,kca_application_id,issued_at'],
                'order_column' => 'received_at',
                'status_column' => 'status',
            ],
            'kca.enrollments' => [
                'permission' => 'kca.enrollments.view',
                'path' => 'kca/enrollments',
                'operation_id' => 'listAdminCatalogKcaEnrollments',
                'model' => KcaEnrollment::class,
                'with' => [
                    ...PersonDisplayName::eager(),
                    'year:id,public_id,name,code',
                    'cohort:id,public_id,name,code',
                    'application:id,public_id',
                    ...PersonDisplayName::eager('mentorAssignments.mentor'),
                ],
                'order_column' => 'starts_on',
            ],
            'kca.evidence' => [
                'permission' => 'kca.evidence.view',
                'path' => 'kca/evidence-submissions',
                'operation_id' => 'listAdminCatalogKcaEvidenceSubmissions',
                'model' => KcaEvidenceSubmission::class,
                'with' => ['enrollment:id,public_id', 'fileAsset:id,public_id', ...PersonDisplayName::eager('submittedBy')],
                'order_column' => 'submitted_at',
            ],
            'kca.assessments' => [
                'permission' => 'kca.assessments.view',
                'path' => 'kca/assessment-results',
                'operation_id' => 'listAdminCatalogKcaAssessmentResults',
                'model' => KcaAssessmentResult::class,
                'with' => [
                    'enrollment:id,public_id,registration_number',
                    ...PersonDisplayName::eager('enrollment.person'),
                    'module:id,public_id,title,code',
                ],
                'order_column' => 'assessed_at',
            ],
            'kca.attendance' => [
                'permission' => 'kca.enrollments.view',
                'path' => 'kca/attendance',
                'operation_id' => 'listAdminCatalogKcaAttendance',
                'model' => KcaAttendance::class,
                'with' => [
                    'enrollment:id,public_id,registration_number',
                    ...PersonDisplayName::eager('enrollment.person'),
                    'lesson:id,public_id,title,code',
                ],
                'order_column' => 'session_on',
                'order_direction' => 'desc',
            ],
            'kca.certificates' => [
                'permission' => 'kca.certificates.view',
                'path' => 'kca/certificates',
                'operation_id' => 'listAdminCatalogKcaCertificates',
                'model' => KcaCertificate::class,
                'with' => [...PersonDisplayName::eager(), 'enrollment:id,public_id'],
                'order_column' => 'issued_at',
            ],
            'kca.years' => [
                'permission' => 'kca.enrollments.view',
                'path' => 'kca/years',
                'operation_id' => 'listAdminCatalogKcaYears',
                'model' => KcaYear::class,
                'order_column' => 'starts_on',
                'order_direction' => 'desc',
                'search_column' => 'name',
            ],
            'kca.cohorts' => [
                'permission' => 'kca.enrollments.view',
                'path' => 'kca/cohorts',
                'operation_id' => 'listAdminCatalogKcaCohorts',
                'model' => KcaCohort::class,
                'with' => ['year:id,public_id,name,code'],
                'with_count' => ['enrollments'],
                'order_column' => 'starts_on',
                'order_direction' => 'desc',
                'search_column' => 'name',
            ],
            'kca.orientation' => [
                'permission' => 'kca.orientation.view',
                'path' => 'kca/orientation-sessions',
                'operation_id' => 'listAdminCatalogKcaOrientationSessions',
                'model' => KcaOrientationSession::class,
                'with' => [
                    'cohort' => fn ($query) => $query->select('id', 'public_id', 'name')->withCount('enrollments'),
                    'location:id,public_id,name',
                ],
                'order_column' => 'starts_at',
                'order_direction' => 'desc',
                'search_column' => 'name',
            ],
            'kca.modules' => [
                'permission' => 'kca.enrollments.view',
                'path' => 'kca/modules',
                'operation_id' => 'listAdminCatalogKcaModules',
                'model' => KcaModule::class,
                'with_count' => ['lessons'],
                'order_column' => 'sequence',
                'order_direction' => 'asc',
                'search_column' => 'title',
            ],
            'kca.lessons' => [
                'permission' => 'kca.enrollments.view',
                'path' => 'kca/lessons',
                'operation_id' => 'listAdminCatalogKcaLessons',
                'model' => KcaLesson::class,
                'with' => ['module:id,public_id,title,code', 'chapters'],
                'order_column' => 'sequence',
                'order_direction' => 'asc',
                'search_column' => 'title',
            ],
            'kca.chapters' => [
                'permission' => 'kca.enrollments.view',
                'path' => 'kca/chapters',
                'operation_id' => 'listAdminCatalogKcaChapters',
                'model' => KcaChapter::class,
                'with' => ['lesson:id,public_id,title,code'],
                'order_column' => 'sequence',
                'order_direction' => 'asc',
                'search_column' => 'title',
            ],
            'kca.prerequisites' => [
                'permission' => 'kca.enrollments.view',
                'path' => 'kca/prerequisites',
                'operation_id' => 'listAdminCatalogKcaPrerequisites',
                'model' => KcaModulePrerequisite::class,
                'with' => [
                    'module:id,public_id,title,code',
                    'prerequisiteModule:id,public_id,title,code',
                ],
                'order_column' => 'created_at',
                'order_direction' => 'desc',
            ],
            'kca.lecturer_assignments' => [
                'permission' => 'kca.enrollments.view',
                'path' => 'kca/lecturer-assignments',
                'operation_id' => 'listAdminCatalogKcaLecturerAssignments',
                'model' => KcaLecturerAssignment::class,
                'with' => [
                    'module:id,public_id,title,code',
                    'cohort:id,public_id,name,code',
                    ...PersonDisplayName::eager('lecturer'),
                ],
                'order_column' => 'starts_at',
                'order_direction' => 'desc',
            ],
            'kca.mentor_assignments' => [
                'permission' => 'kca.enrollments.view',
                'path' => 'kca/mentor-assignments',
                'operation_id' => 'listAdminCatalogKcaMentorAssignments',
                'model' => KcaMentorAssignment::class,
                'with' => [
                    'enrollment:id,public_id',
                    ...PersonDisplayName::eager('mentor'),
                    ...PersonDisplayName::eager('enrollment.person'),
                ],
                'order_column' => 'starts_at',
                'order_direction' => 'desc',
            ],
            'press.publications' => [
                'permission' => 'press.publications.view',
                'path' => 'press/publications',
                'operation_id' => 'listAdminCatalogPressPublications',
                'model' => PressPublication::class,
                'with' => PersonDisplayName::eager('contributors.person'),
                'order_column' => 'updated_at',
                'search_column' => 'title',
                'status_column' => 'status',
            ],
            'press.translations' => [
                'permission' => 'press.translations.view',
                'path' => 'press/translations',
                'operation_id' => 'listAdminCatalogPressTranslations',
                'model' => PressTranslation::class,
                'with' => ['publication:id,public_id,title'],
                'order_column' => 'updated_at',
                'search_column' => 'translated_title',
                'status_column' => 'status',
            ],
            'press.contributors' => [
                'permission' => 'press.publications.view',
                'path' => 'press/contributors',
                'operation_id' => 'listAdminCatalogPressContributors',
                'model' => PressPublicationContributor::class,
                'with' => [
                    'publication:id,public_id,title',
                    ...PersonDisplayName::eager(),
                ],
                'order_column' => 'updated_at',
                'search_column' => 'public_id',
            ],
            'press.authors' => [
                'permission' => 'press.publications.view',
                'path' => 'press/authors',
                'operation_id' => 'listAdminCatalogPressAuthors',
                'model' => PressAuthor::class,
                'with' => PersonDisplayName::eager(),
                'order_column' => 'updated_at',
                'search_column' => 'display_name',
                'status_column' => 'status',
            ],
            'press.assets' => [
                'permission' => 'press.publications.view',
                'path' => 'press/assets',
                'operation_id' => 'listAdminCatalogPressAssets',
                'model' => PressPublicationAsset::class,
                'with' => ['publication:id,public_id,title', 'fileAsset:id,public_id'],
                'order_column' => 'updated_at',
            ],
            'press.reviews' => [
                'permission' => 'press.publications.view',
                'path' => 'press/reviews',
                'operation_id' => 'listAdminCatalogPressReviews',
                'model' => PressPublicationReview::class,
                'with' => [
                    'publication:id,public_id,title',
                    ...PersonDisplayName::eager('reviewer'),
                ],
                'order_column' => 'decided_at',
                'status_column' => 'decision',
            ],
            'events.events' => [
                'permission' => 'events.events.view',
                'path' => 'events/events',
                'operation_id' => 'listAdminCatalogEvents',
                'model' => MinistryEvent::class,
                'with' => ['location:id,public_id,name'],
                'order_column' => 'starts_at',
                'search_column' => 'name',
            ],
            'events.registrations' => [
                'permission' => 'events.registrations.view',
                'path' => 'events/registrations',
                'operation_id' => 'listAdminCatalogEventRegistrations',
                'model' => EventRegistration::class,
                'with' => ['event:id,public_id,name', ...PersonDisplayName::eager()],
                'order_column' => 'registered_at',
                'status_column' => 'status',
            ],
            'finance.payment_intents' => [
                'permission' => 'finance.payment_intents.view',
                'path' => 'finance/payment-intents',
                'operation_id' => 'listAdminCatalogPaymentIntents',
                'model' => PaymentIntent::class,
                'with' => [...PersonDisplayName::eager('payer'), 'proofFileAsset:id,public_id'],
                'order_column' => 'created_at',
                'status_column' => 'status',
                'purpose_column' => 'purpose_code',
            ],
            'finance.payment_transactions' => [
                'permission' => 'finance.payment_transactions.view',
                'path' => 'finance/payment-transactions',
                'operation_id' => 'listAdminCatalogPaymentTransactions',
                'model' => PaymentTransaction::class,
                'with' => [
                    'intent:id,public_id,purpose_code,status,payer_person_id,currency,amount_minor',
                    ...PersonDisplayName::eager('intent.payer'),
                    'reconciliation:id,public_id,status,payment_transaction_id',
                    'receipt:id,public_id,receipt_number,payment_transaction_id',
                ],
                'order_column' => 'occurred_at',
                'status_column' => 'status',
                'status_relation' => 'intent',
                'purpose_column' => 'purpose_code',
                'purpose_relation' => 'intent',
                'search_columns' => ['public_id', 'provider_code'],
                'search_profile_relation' => 'intent.payer.profile',
            ],
            'finance.payment_reconciliations' => [
                'permission' => 'finance.payment_reconciliations.view',
                'path' => 'finance/payment-reconciliations',
                'operation_id' => 'listAdminCatalogPaymentReconciliations',
                'model' => PaymentReconciliation::class,
                'with' => [
                    'transaction:id,public_id,provider_code,amount_minor,currency,occurred_at,payment_intent_id',
                    'transaction.intent:id,public_id,purpose_code,payer_person_id',
                    ...PersonDisplayName::eager('transaction.intent.payer'),
                ],
                'order_column' => 'reconciled_at',
                'status_column' => 'status',
            ],
            'finance.payment_receipts' => [
                'permission' => 'finance.payment_receipts.view',
                'path' => 'finance/payment-receipts',
                'operation_id' => 'listAdminCatalogPaymentReceipts',
                'model' => PaymentReceipt::class,
                'with' => [
                    'transaction:id,public_id,provider_code,amount_minor,currency,occurred_at,payment_intent_id',
                    'transaction.intent:id,public_id,purpose_code,payer_person_id',
                    ...PersonDisplayName::eager('transaction.intent.payer'),
                ],
                'order_column' => 'issued_at',
            ],
            'finance.payment_refunds' => [
                'permission' => 'finance.payment_refunds.view',
                'path' => 'finance/payment-refunds',
                'operation_id' => 'listAdminCatalogPaymentRefunds',
                'model' => PaymentRefund::class,
                'with' => [
                    'transaction:id,public_id,provider_code,amount_minor,currency,occurred_at,payment_intent_id',
                    'transaction.intent:id,public_id,purpose_code,payer_person_id',
                    ...PersonDisplayName::eager('transaction.intent.payer'),
                ],
                'order_column' => 'requested_at',
                'status_column' => 'status',
            ],
            'finance.payment_disputes' => [
                'permission' => 'finance.payment_disputes.view',
                'path' => 'finance/payment-disputes',
                'operation_id' => 'listAdminCatalogPaymentDisputes',
                'model' => PaymentDispute::class,
                'with' => [
                    'transaction:id,public_id,provider_code,amount_minor,currency,occurred_at,payment_intent_id',
                    'transaction.intent:id,public_id,purpose_code,payer_person_id',
                    ...PersonDisplayName::eager('transaction.intent.payer'),
                ],
                'order_column' => 'occurred_at',
                'status_column' => 'status',
            ],
            'communications.templates' => [
                'permission' => 'communications.templates.view',
                'path' => 'communications/templates',
                'operation_id' => 'listAdminCatalogCommunicationTemplates',
                'model' => CommunicationTemplate::class,
                'order_column' => 'created_at',
                'search_column' => 'code',
                'search_columns' => ['code', 'subject'],
            ],
            'communications.audiences' => [
                'permission' => 'communications.audiences.view',
                'path' => 'communications/audiences',
                'operation_id' => 'listAdminCatalogCommunicationAudiences',
                'model' => CommunicationAudience::class,
                'order_column' => 'created_at',
                'search_column' => 'code',
                'search_columns' => ['code', 'name'],
            ],
            'communications.broadcasts' => [
                'permission' => 'communications.broadcasts.view',
                'path' => 'communications/broadcasts',
                'operation_id' => 'listAdminCatalogCommunicationBroadcasts',
                'model' => CommunicationBroadcast::class,
                'with' => ['template:id,public_id,code,subject', 'audience:id,public_id,code,name'],
                'order_column' => 'created_at',
                'status_column' => 'status',
                'search_columns' => ['purpose'],
            ],
            'communications.deliveries' => [
                'permission' => 'communications.deliveries.view',
                'path' => 'communications/delivery-attempts',
                'operation_id' => 'listAdminCatalogCommunicationDeliveries',
                'model' => CommunicationDeliveryAttempt::class,
                'with' => [
                    'recipient:id,public_id,communication_broadcast_id',
                    'recipient.broadcast:id,public_id,purpose,channel,status,communication_audience_id,communication_template_id',
                    'recipient.broadcast.audience:id,public_id,code,name',
                    'recipient.broadcast.template:id,public_id,code,subject',
                ],
                'order_column' => 'attempted_at',
                'status_column' => 'status',
            ],
            'communications.notifications' => [
                'permission' => 'communications.notifications.view',
                'path' => 'communications/notifications',
                'operation_id' => 'listAdminCatalogCommunicationNotifications',
                'model' => CommunicationNotification::class,
                'with' => [...PersonDisplayName::eager(), 'user:id,public_id,name,email'],
                'order_column' => 'created_at',
            ],
            'reporting.alert_rules' => [
                'permission' => 'reporting.alert_rules.view',
                'path' => 'reporting/alert-rules',
                'operation_id' => 'listAdminCatalogAlertRules',
                'model' => AlertRule::class,
                'order_column' => 'created_at',
                'search_column' => 'code',
            ],
            'reporting.alert_occurrences' => [
                'permission' => 'reporting.alert_occurrences.view',
                'path' => 'reporting/alert-occurrences',
                'operation_id' => 'listAdminCatalogAlertOccurrences',
                'model' => AlertOccurrence::class,
                'with' => ['rule:id,public_id,code,title'],
                'order_column' => 'opened_at',
                'status_column' => 'status',
            ],
            'privacy.data_subject_requests' => [
                'permission' => 'privacy.data_subject_requests.view',
                'path' => 'privacy/data-subject-requests',
                'operation_id' => 'listAdminCatalogDataSubjectRequests',
                'model' => DataSubjectRequest::class,
                'with' => PersonDisplayName::eager(),
                'order_column' => 'requested_at',
                'status_column' => 'status',
            ],
            'platform.files' => [
                'permission' => 'platform.files.view',
                'path' => 'platform/files',
                'operation_id' => 'listAdminCatalogFileAssets',
                'model' => FileAsset::class,
                'with' => PersonDisplayName::eager('owner'),
                'order_column' => 'created_at',
                'status_column' => 'status',
            ],
            'safeguarding.incidents' => [
                'permission' => 'safeguarding.incidents.report',
                'path' => 'safeguarding/incidents',
                'operation_id' => 'listAdminCatalogSafeguardingIncidents',
                'model' => SafeguardingIncident::class,
                'with' => PersonDisplayName::eager('subject'),
                'order_column' => 'occurred_at',
                'status_column' => 'status',
            ],
            'safeguarding.guardians' => [
                'permission' => 'safeguarding.guardians.register',
                'path' => 'safeguarding/guardian-relationships',
                'operation_id' => 'listAdminCatalogGuardianRelationships',
                'model' => GuardianRelationship::class,
                'with' => [
                    ...PersonDisplayName::eager('guardian'),
                    ...PersonDisplayName::eager('child'),
                ],
                'order_column' => 'created_at',
                'status_column' => 'status',
            ],
            'safeguarding.child_profiles' => [
                'permission' => 'safeguarding.guardians.register',
                'path' => 'safeguarding/child-profiles',
                'operation_id' => 'listAdminCatalogChildProfiles',
                'model' => ChildProfile::class,
                'with' => PersonDisplayName::eager('person'),
                'order_column' => 'updated_at',
                'status_column' => 'minor_status',
                'search_profile_relation' => 'person.profile',
            ],
        ];
    }

    /**
     * @return array{
     *     permission: string,
     *     path: string,
     *     operation_id: string,
     *     model: class-string<Model>,
     *     with?: array<int, string>,
     *     order_column: string,
     *     order_direction?: 'asc'|'desc',
     *     search_column?: string,
     *     search_columns?: array<int, string>,
     *     search_profile_relation?: string,
     *     status_column?: string,
     *     status_relation?: string,
     *     purpose_column?: string,
     *     purpose_relation?: string
     * }
     */
    public function definition(string $key): array
    {
        $definition = $this->definitions()[$key] ?? null;

        if ($definition === null) {
            throw new NotFoundHttpException;
        }

        return $definition;
    }

    /**
     * @param  array{search?: string, status?: string, purpose?: string}  $filters
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(string $key, array $filters, int $perPage): LengthAwarePaginator
    {
        $definition = $this->definition($key);
        /** @var Builder<Model> $query */
        $query = $definition['model']::query();

        if (isset($definition['with'])) {
            $query->with($definition['with']);
        }

        if (isset($definition['with_count'])) {
            $query->withCount($definition['with_count']);
        }

        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $searchColumns = $definition['search_columns'] ?? [];
            if ($searchColumns === [] && isset($definition['search_column'])) {
                $searchColumns = [$definition['search_column']];
            }
            $term = '%'.trim((string) $filters['search']).'%';
            if ($searchColumns !== [] || isset($definition['search_profile_relation'])) {
                $query->where(function (Builder $outer) use ($searchColumns, $term, $definition): void {
                    foreach ($searchColumns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $outer->{$method}($column, 'like', $term);
                    }
                    if (isset($definition['search_profile_relation'])) {
                        $outer->orWhereHas(
                            $definition['search_profile_relation'],
                            function (Builder $profile) use ($term): void {
                                $profile->where('given_name', 'like', $term)
                                    ->orWhere('family_name', 'like', $term)
                                    ->orWhere('preferred_name', 'like', $term);
                            },
                        );
                    }
                });
            }
        }

        if (isset($definition['status_column'], $filters['status'])) {
            $this->applyRelatedColumnFilter(
                $query,
                $definition['status_relation'] ?? null,
                $definition['status_column'],
                $filters['status'],
            );
        }

        if (isset($definition['purpose_column'], $filters['purpose'])) {
            $this->applyRelatedColumnFilter(
                $query,
                $definition['purpose_relation'] ?? null,
                $definition['purpose_column'],
                $filters['purpose'],
            );
        }

        $direction = $definition['order_direction'] ?? 'desc';

        return $query->orderBy($definition['order_column'], $direction)->paginate($perPage);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyRelatedColumnFilter(Builder $query, ?string $relation, string $column, mixed $value): void
    {
        if ($relation === null || $relation === '') {
            $query->where($column, $value);

            return;
        }

        $query->whereHas($relation, function (Builder $related) use ($column, $value): void {
            $related->where($column, $value);
        });
    }
}
