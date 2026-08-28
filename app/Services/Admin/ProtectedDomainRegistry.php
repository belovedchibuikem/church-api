<?php

namespace App\Services\Admin;

use App\Models\AlertOccurrence;
use App\Models\AlertRule;
use App\Models\CommunicationAudience;
use App\Models\CommunicationBroadcast;
use App\Models\CommunicationDeliveryAttempt;
use App\Models\CommunicationNotification;
use App\Models\CommunicationTemplate;
use App\Models\DataSubjectRequest;
use App\Models\EventRegistration;
use App\Models\FileAsset;
use App\Models\KcaApplication;
use App\Models\KcaAssessmentResult;
use App\Models\KcaCertificate;
use App\Models\KcaEnrollment;
use App\Models\KcaEvidenceSubmission;
use App\Models\MinistryEvent;
use App\Models\PaymentDispute;
use App\Models\PaymentIntent;
use App\Models\PaymentReceipt;
use App\Models\PaymentReconciliation;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\PressPublication;
use App\Models\PressTranslation;
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
     *     status_column?: string
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
                'with' => PersonDisplayName::eager(),
                'order_column' => 'received_at',
                'status_column' => 'status',
            ],
            'kca.enrollments' => [
                'permission' => 'kca.enrollments.view',
                'path' => 'kca/enrollments',
                'operation_id' => 'listAdminCatalogKcaEnrollments',
                'model' => KcaEnrollment::class,
                'with' => [...PersonDisplayName::eager(), 'year:id,public_id', 'cohort:id,public_id', 'application:id,public_id'],
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
                'with' => ['enrollment:id,public_id', 'module:id,public_id'],
                'order_column' => 'assessed_at',
            ],
            'kca.certificates' => [
                'permission' => 'kca.certificates.view',
                'path' => 'kca/certificates',
                'operation_id' => 'listAdminCatalogKcaCertificates',
                'model' => KcaCertificate::class,
                'with' => [...PersonDisplayName::eager(), 'enrollment:id,public_id'],
                'order_column' => 'issued_at',
            ],
            'press.publications' => [
                'permission' => 'press.publications.view',
                'path' => 'press/publications',
                'operation_id' => 'listAdminCatalogPressPublications',
                'model' => PressPublication::class,
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
                'with' => PersonDisplayName::eager('payer'),
                'order_column' => 'created_at',
                'status_column' => 'status',
            ],
            'finance.payment_transactions' => [
                'permission' => 'finance.payment_transactions.view',
                'path' => 'finance/payment-transactions',
                'operation_id' => 'listAdminCatalogPaymentTransactions',
                'model' => PaymentTransaction::class,
                'with' => ['intent:id,public_id'],
                'order_column' => 'occurred_at',
            ],
            'finance.payment_reconciliations' => [
                'permission' => 'finance.payment_reconciliations.view',
                'path' => 'finance/payment-reconciliations',
                'operation_id' => 'listAdminCatalogPaymentReconciliations',
                'model' => PaymentReconciliation::class,
                'with' => ['transaction:id,public_id'],
                'order_column' => 'reconciled_at',
                'status_column' => 'status',
            ],
            'finance.payment_receipts' => [
                'permission' => 'finance.payment_receipts.view',
                'path' => 'finance/payment-receipts',
                'operation_id' => 'listAdminCatalogPaymentReceipts',
                'model' => PaymentReceipt::class,
                'with' => ['transaction:id,public_id'],
                'order_column' => 'issued_at',
            ],
            'finance.payment_refunds' => [
                'permission' => 'finance.payment_refunds.view',
                'path' => 'finance/payment-refunds',
                'operation_id' => 'listAdminCatalogPaymentRefunds',
                'model' => PaymentRefund::class,
                'with' => ['transaction:id,public_id'],
                'order_column' => 'requested_at',
                'status_column' => 'status',
            ],
            'finance.payment_disputes' => [
                'permission' => 'finance.payment_disputes.view',
                'path' => 'finance/payment-disputes',
                'operation_id' => 'listAdminCatalogPaymentDisputes',
                'model' => PaymentDispute::class,
                'with' => ['transaction:id,public_id'],
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
            ],
            'communications.audiences' => [
                'permission' => 'communications.audiences.view',
                'path' => 'communications/audiences',
                'operation_id' => 'listAdminCatalogCommunicationAudiences',
                'model' => CommunicationAudience::class,
                'order_column' => 'created_at',
                'search_column' => 'code',
            ],
            'communications.broadcasts' => [
                'permission' => 'communications.broadcasts.view',
                'path' => 'communications/broadcasts',
                'operation_id' => 'listAdminCatalogCommunicationBroadcasts',
                'model' => CommunicationBroadcast::class,
                'with' => ['template:id,public_id,code', 'audience:id,public_id,code'],
                'order_column' => 'created_at',
                'status_column' => 'status',
            ],
            'communications.deliveries' => [
                'permission' => 'communications.deliveries.view',
                'path' => 'communications/delivery-attempts',
                'operation_id' => 'listAdminCatalogCommunicationDeliveries',
                'model' => CommunicationDeliveryAttempt::class,
                'with' => ['recipient:id,public_id'],
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
                'with' => ['rule:id,public_id,code'],
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
     *     status_column?: string
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
     * @param  array{search?: string, status?: string}  $filters
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

        if (isset($definition['search_column'], $filters['search'])) {
            $query->where(
                $definition['search_column'],
                'like',
                '%'.trim((string) $filters['search']).'%',
            );
        }

        if (isset($definition['status_column'], $filters['status'])) {
            $query->where($definition['status_column'], $filters['status']);
        }

        $direction = $definition['order_direction'] ?? 'desc';

        return $query->orderBy($definition['order_column'], $direction)->paginate($perPage);
    }
}
