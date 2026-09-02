<?php

namespace App\Support\Kca;

use App\Kca\KcaApplicationState;
use App\Models\KcaApplication;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use App\Support\People\CreatePersonAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateAdminKcaApplicationAction
{
    public function __construct(
        private CreatePersonAction $createPerson,
        private ProvisionKcaStudentLoginAction $provisionLogin,
        private RequestKcaLeadershipRecommendationAction $requestRecommendation,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    /**
     * @param  array<string, string|null>  $applicationData
     */
    public function handle(
        ?KcaApplication $existingApplication,
        ?Person $person,
        array $applicationData,
        User $actor,
        ?string $givenName = null,
        ?string $familyName = null,
        ?string $email = null,
        ?string $phone = null,
        bool $finalize = true,
        bool $createLogin = false,
        ?string $password = null,
    ): KcaApplication {
        return DB::transaction(function () use ($existingApplication, $person, $applicationData, $actor, $givenName, $familyName, $email, $phone, $finalize, $createLogin, $password): KcaApplication {
            if ($existingApplication !== null) {
                $application = KcaApplication::query()
                    ->with('person')
                    ->lockForUpdate()
                    ->findOrFail($existingApplication->getKey());
                $status = $application->status instanceof KcaApplicationState
                    ? $application->status
                    : KcaApplicationState::from((string) $application->status);

                if ($status !== KcaApplicationState::Draft && $status !== KcaApplicationState::InformationRequired) {
                    throw new InvalidArgumentException('Only draft applications can be updated.');
                }

                $applicant = $application->person;
            } else {
                $applicant = $person ?? $this->createPerson->handle([
                    'given_name' => (string) $givenName,
                    'family_name' => (string) $familyName,
                    'email' => $email,
                    'phone' => $phone,
                ], $actor);

                $openApplication = KcaApplication::query()
                    ->where('person_id', $applicant->getKey())
                    ->whereNotIn('status', [
                        KcaApplicationState::NotAccepted->value,
                        KcaApplicationState::Withdrawn->value,
                        KcaApplicationState::Revoked->value,
                    ])
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if ($openApplication !== null) {
                    $status = $openApplication->status instanceof KcaApplicationState
                        ? $openApplication->status
                        : KcaApplicationState::from((string) $openApplication->status);

                    if ($status !== KcaApplicationState::Draft && $status !== KcaApplicationState::InformationRequired) {
                        throw new InvalidArgumentException('This person already has an active KCA application.');
                    }
                }

                $application = $openApplication ?? new KcaApplication([
                    'person_id' => $applicant->getKey(),
                    'received_at' => now()->utc(),
                ]);
            }

            $application->application_data = $this->applicantSafeData($applicationData);
            $application->status = $finalize ? KcaApplicationState::Received : KcaApplicationState::Draft;
            if ($finalize && $application->received_at === null) {
                $application->received_at = now()->utc();
            }
            $application->save();

            $this->syncLeadershipRecommendation($application, $application->application_data ?? [], $actor);

            if ($finalize && $createLogin) {
                $loginEmail = trim((string) $email);
                if ($loginEmail === '') {
                    throw new InvalidArgumentException('Email is required when creating a student login account.');
                }
                if ($password === null || $password === '') {
                    throw new InvalidArgumentException('Password is required when creating a student login account.');
                }

                $this->provisionLogin->handle($applicant, $loginEmail, $password, $actor);
            }

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.application.created_by_admin',
                actor: $actor,
                targetType: 'kca_application',
                targetId: $application->public_id,
                scopeType: 'global',
                scopeId: 'platform',
                metadata: [
                    'person_id' => $applicant->public_id,
                    'status' => $application->status->value,
                    'finalize' => $finalize,
                    'create_login' => $createLogin,
                ],
            ));

            return $application->fresh(['person.profile']);
        });
    }

    /** @param  array<string, mixed>  $incoming */
    private function applicantSafeData(array $incoming): array
    {
        foreach ([
            'recommendation',
            'recommendation_comments',
            'recommendation_approved',
            'recommendation_status',
            'approved',
            'leadership_approved',
            'statement',
            'verifier_token',
        ] as $denied) {
            unset($incoming[$denied]);
        }

        return array_map(
            static fn (mixed $value): ?string => $value === null ? null : (string) $value,
            $incoming,
        );
    }

    /** @param  array<string, mixed>  $incoming */
    private function syncLeadershipRecommendation(KcaApplication $application, array $incoming, User $actor): void
    {
        $email = isset($incoming['recommender_email']) ? trim((string) $incoming['recommender_email']) : '';
        $name = isset($incoming['recommender_name']) ? trim((string) $incoming['recommender_name']) : '';
        if ($email === '' || $name === '') {
            return;
        }

        $this->requestRecommendation->handle(
            $application,
            $name,
            $email,
            isset($incoming['recommender_position']) ? (string) $incoming['recommender_position'] : (isset($incoming['recommender_role']) ? (string) $incoming['recommender_role'] : null),
            isset($incoming['recommender_phone']) ? (string) $incoming['recommender_phone'] : null,
            $actor,
        );
    }
}
