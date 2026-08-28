<?php

namespace App\Support\Church;

use App\Church\HomeChurchApplicationStatus;
use App\Church\HomeChurchStatus;
use App\Models\Church;
use App\Models\HomeChurch;
use App\Models\HomeChurchApplication;
use App\Models\HomeChurchApplicationTransition;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class TransitionHomeChurchApplicationAction
{
    public function __construct(
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(
        HomeChurchApplication $application,
        HomeChurchApplicationStatus $targetStatus,
        string $reasonCode,
        User $actor,
    ): HomeChurchApplication {
        $reason = new StableReasonCode($reasonCode);

        return DB::transaction(function () use (
            $application,
            $targetStatus,
            $reason,
            $actor,
        ): HomeChurchApplication {
            $lockedApplication = HomeChurchApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->getKey());
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $church = Church::query()->lockForUpdate()->findOrFail($lockedApplication->church_id);
            $currentStatus = $lockedApplication->status;

            if ($currentStatus === $targetStatus) {
                if ($targetStatus === HomeChurchApplicationStatus::Active && $lockedApplication->home_church_id === null) {
                    throw new LogicException('An active Home Church application must be linked to a Home Church.');
                }

                return $lockedApplication;
            }

            if (! $currentStatus->canTransitionTo($targetStatus)) {
                throw new InvalidArgumentException(
                    "Home Church applications cannot transition from {$currentStatus->value} to {$targetStatus->value}.",
                );
            }

            $homeChurch = $this->applyHomeChurchState($lockedApplication, $targetStatus, $lockedActor);
            $occurredAt = now()->utc();
            $lockedApplication->status = $targetStatus;
            $lockedApplication->active_marker = $targetStatus->isOpen() ? 1 : null;
            $lockedApplication->status_changed_at = $occurredAt;
            $lockedApplication->save();

            $correlationId = Context::get('correlation_id');
            HomeChurchApplicationTransition::query()->create([
                'home_church_application_id' => $lockedApplication->getKey(),
                'actor_user_id' => $lockedActor->getKey(),
                'from_status' => $currentStatus,
                'to_status' => $targetStatus,
                'reason_code' => $reason->value,
                'correlation_id' => is_string($correlationId) && Str::isUuid($correlationId)
                    ? $correlationId
                    : null,
                'occurred_at' => $occurredAt,
            ]);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'home_church.application.status_changed',
                actor: $lockedActor,
                targetType: 'home_church_application',
                targetId: $lockedApplication->public_id,
                scopeType: $homeChurch === null ? 'church' : 'home_church',
                scopeId: $homeChurch?->public_id ?? $church->public_id,
                metadata: [
                    'from_status' => $currentStatus->value,
                    'to_status' => $targetStatus->value,
                    'reason_code' => $reason->value,
                ],
            ));

            return $lockedApplication;
        }, attempts: 3);
    }

    private function applyHomeChurchState(
        HomeChurchApplication $application,
        HomeChurchApplicationStatus $targetStatus,
        User $actor,
    ): ?HomeChurch {
        $homeChurch = $application->home_church_id === null
            ? null
            : HomeChurch::query()->lockForUpdate()->findOrFail($application->home_church_id);

        if ($targetStatus === HomeChurchApplicationStatus::Active && $homeChurch === null) {
            $duplicateExists = HomeChurch::query()
                ->where('location_id', $application->location_id)
                ->where('name', $application->proposed_name)
                ->lockForUpdate()
                ->exists();

            if ($duplicateExists) {
                throw new InvalidArgumentException('A Home Church with this name already exists at the location.');
            }

            $homeChurch = new HomeChurch([
                'church_id' => $application->church_id,
                'leader_person_id' => $application->applicant_person_id,
                'location_id' => $application->location_id,
                'administrative_unit_id' => $application->administrative_unit_id,
                'name' => $application->proposed_name,
            ]);
            $homeChurch->status = HomeChurchStatus::Active;
            $homeChurch->save();
            $application->home_church_id = $homeChurch->getKey();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'home_church.created',
                actor: $actor,
                targetType: 'home_church',
                targetId: $homeChurch->public_id,
                scopeType: 'home_church',
                scopeId: $homeChurch->public_id,
                metadata: [
                    'church_id' => $application->church()->value('public_id'),
                    'application_id' => $application->public_id,
                ],
            ));
        } elseif ($targetStatus === HomeChurchApplicationStatus::Active && $homeChurch !== null) {
            $homeChurch->status = HomeChurchStatus::Active;
            $homeChurch->save();
        } elseif ($targetStatus === HomeChurchApplicationStatus::Suspended && $homeChurch !== null) {
            $homeChurch->status = HomeChurchStatus::Suspended;
            $homeChurch->save();
        } elseif ($targetStatus === HomeChurchApplicationStatus::Closed && $homeChurch !== null) {
            $homeChurch->status = HomeChurchStatus::Closed;
            $homeChurch->save();
        }

        return $homeChurch;
    }
}
