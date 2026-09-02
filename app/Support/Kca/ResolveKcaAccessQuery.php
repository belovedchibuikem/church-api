<?php

namespace App\Support\Kca;

use App\Kca\KcaApplicationState;
use App\Models\AuditEvent;
use App\Models\KcaApplication;
use App\Models\KcaEnrollment;
use App\Models\Person;

class ResolveKcaAccessQuery
{
    /** @return array<string, mixed> */
    public function handle(Person $person): array
    {
        $enrollment = KcaEnrollment::query()
            ->with(['year:id,public_id,name', 'cohort:id,public_id,name,timezone'])
            ->where('person_id', $person->getKey())
            ->latest('starts_on')
            ->first();

        $application = KcaApplication::query()
            ->with('leadershipRecommendation')
            ->where('person_id', $person->getKey())
            ->latest('id')
            ->first();

        if ($enrollment !== null) {
            return [
                'state' => 'activated_student',
                'destination' => 'student_dashboard',
                'label' => 'Activated student',
                'application' => $application ? $this->applicationPayload($application, includeAnswers: false) : null,
                'enrollment' => [
                    'id' => $enrollment->public_id,
                    'year' => $enrollment->year?->name,
                    'cohort' => $enrollment->cohort?->name,
                    'starts_on' => $enrollment->starts_on?->toDateString(),
                ],
                'timeline' => $this->timeline($application),
                'permitted_actions' => ['open_dashboard', 'continue_learning'],
                'next_step' => 'Open your KCA student dashboard.',
            ];
        }

        if ($application === null) {
            return [
                'state' => 'none',
                'destination' => 'overview',
                'label' => 'Not applied',
                'application' => null,
                'enrollment' => null,
                'timeline' => [],
                'permitted_actions' => ['apply'],
                'next_step' => 'Review eligibility and start an application.',
            ];
        }

        $status = $application->status instanceof KcaApplicationState
            ? $application->status
            : KcaApplicationState::from((string) $application->status);

        return [
            'state' => $status->value,
            'destination' => $status === KcaApplicationState::Accepted ? 'admission_letter' : $status->destination(),
            'label' => $status->publicLabel(),
            'application' => $this->applicationPayload($application, includeAnswers: $status->allowsApplicantEdit()),
            'enrollment' => null,
            'timeline' => $this->timeline($application),
            'permitted_actions' => $this->actions($status),
            'next_step' => $this->nextStep($status, $application),
        ];
    }

    /** @return array<string, mixed> */
    private function applicationPayload(KcaApplication $application, bool $includeAnswers): array
    {
        $status = $application->status instanceof KcaApplicationState
            ? $application->status
            : KcaApplicationState::from((string) $application->status);

        $payload = [
            'id' => $application->public_id,
            'status' => $status->value,
            'label' => $status->publicLabel(),
            'received_at' => $application->received_at?->utc()->toIso8601String(),
            'reviewed_at' => $application->reviewed_at?->utc()->toIso8601String(),
            'orientation_completed_at' => $application->orientation_completed_at?->utc()->toIso8601String(),
            'orientation_progress' => $application->orientation_progress ?? [],
            'recommendation' => $this->recommendationPayload($application),
        ];
        if ($includeAnswers) {
            $payload['application_data'] = $application->application_data;
        }

        return $payload;
    }

    /** @return array<string, mixed>|null */
    private function recommendationPayload(KcaApplication $application): ?array
    {
        $row = $application->leadershipRecommendation;
        if ($row === null) {
            return null;
        }

        return [
            'id' => $row->public_id,
            'status' => $row->status,
            'recommender_name' => $row->recommender_name,
            'recommender_role' => $row->recommender_role,
            'submitted_at' => $row->submitted_at?->utc()->toIso8601String(),
            'verified_at' => $row->verified_at?->utc()->toIso8601String(),
        ];
    }

    /** @return list<array{at: string|null, action: string, from: mixed, to: mixed}> */
    private function timeline(?KcaApplication $application): array
    {
        if ($application === null) {
            return [];
        }

        return AuditEvent::query()
            ->where('target_type', 'kca_application')
            ->where('target_id', $application->public_id)
            ->orderBy('id')
            ->get(['action', 'metadata', 'occurred_at'])
            ->map(fn (AuditEvent $event): array => [
                'at' => $event->occurred_at?->utc()->toIso8601String(),
                'action' => $event->action,
                'from' => data_get($event->metadata, 'from'),
                'to' => data_get($event->metadata, 'to'),
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function actions(KcaApplicationState $status): array
    {
        return match ($status) {
            KcaApplicationState::Draft => ['resume', 'withdraw'],
            KcaApplicationState::InformationRequired => ['correct', 'withdraw'],
            KcaApplicationState::Received, KcaApplicationState::Reviewed => ['view_progress'],
            KcaApplicationState::Interview => ['view_progress', 'complete_orientation'],
            KcaApplicationState::ProvisionallyAccepted => ['view_conditions'],
            KcaApplicationState::Accepted => ['view_letter'],
            default => ['view_decision'],
        };
    }

    private function nextStep(KcaApplicationState $status, KcaApplication $application): string
    {
        return match ($status) {
            KcaApplicationState::Draft => 'Resume your application at the last saved step.',
            KcaApplicationState::Received, KcaApplicationState::Reviewed => 'Track admission progress. Reviewers will contact you if more information is needed.',
            KcaApplicationState::InformationRequired => 'Provide the requested information to continue review.',
            KcaApplicationState::Interview => $application->orientation_completed_at !== null
                ? 'Orientation is complete. Your application is awaiting a final admission decision.'
                : 'Complete orientation requirements, then submit to continue review.',
            KcaApplicationState::ProvisionallyAccepted => 'Review outstanding conditions on your provisional offer.',
            KcaApplicationState::Accepted => 'Read your admission letter. Learning unlocks after activation.',
            KcaApplicationState::Deferred => 'See the next review window and reapply rules.',
            KcaApplicationState::NotAccepted => 'See the decision and any permitted reapply policy.',
            KcaApplicationState::Withdrawn => 'This application is closed.',
            KcaApplicationState::Suspended, KcaApplicationState::Revoked => 'Contact authorised KCA support. Learning access is restricted.',
        };
    }
}
