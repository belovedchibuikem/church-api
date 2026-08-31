<?php

namespace App\Support\Church;

use App\Church\HomeChurchApplicationStatus;
use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\HomeChurchApplication;
use App\Models\Location;
use App\Models\Person;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateHomeChurchApplicationAction
{
    public function __construct(
        private ChurchLocationValidator $locationValidator,
        private RecordAuditEventAction $recordAuditEvent,
    ) {}

    public function handle(HomeChurchApplicationData $data, ?User $actor = null): HomeChurchApplication
    {
        return DB::transaction(function () use ($data, $actor): HomeChurchApplication {
            $applicant = Person::query()->lockForUpdate()->findOrFail($data->applicant->getKey());
            $church = Church::query()->lockForUpdate()->findOrFail($data->church->getKey());
            $churchUnit = AdministrativeUnit::query()
                ->lockForUpdate()
                ->findOrFail($church->administrative_unit_id);
            $location = Location::query()->lockForUpdate()->findOrFail($data->location->getKey());
            $unit = AdministrativeUnit::query()
                ->lockForUpdate()
                ->findOrFail($data->administrativeUnit->getKey());
            $this->locationValidator->assertAligned($location, $unit);

            if ($churchUnit->country_id !== $unit->country_id) {
                throw new InvalidArgumentException('A Home Church application must be in the church country.');
            }

            $openApplicationExists = HomeChurchApplication::query()
                ->whereBelongsTo($applicant, 'applicant')
                ->whereBelongsTo($church)
                ->where('active_marker', 1)
                ->lockForUpdate()
                ->exists();

            if ($openApplicationExists) {
                throw new InvalidArgumentException('The applicant already has an open Home Church application for this church.');
            }

            $now = now()->utc();
            $application = new HomeChurchApplication([
                'applicant_person_id' => $applicant->getKey(),
                'church_id' => $church->getKey(),
                'location_id' => $location->getKey(),
                'administrative_unit_id' => $unit->getKey(),
                'proposed_name' => $data->proposedName,
                'residence_family_name' => $data->residenceFamilyName,
                'expected_participants' => $data->expectedParticipants,
                'meeting_day' => $data->meetingDay,
                'meeting_time' => $data->meetingTime,
                'meeting_schedules' => HomeChurchMeetingSchedules::toStorage($data->meetingSchedules),
                'contact_email' => $data->contactEmail,
                'contact_phone' => $data->contactPhone,
                'guidelines_agreed_at' => $data->guidelinesAgreedAt->utc(),
            ]);
            $application->status = HomeChurchApplicationStatus::Draft;
            $application->active_marker = 1;
            $application->status_changed_at = $now;
            $application->save();

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'home_church.application.created',
                actor: $actor,
                targetType: 'home_church_application',
                targetId: $application->public_id,
                scopeType: 'church',
                scopeId: $church->public_id,
                metadata: [
                    'status' => HomeChurchApplicationStatus::Draft->value,
                    'location_id' => $location->public_id,
                    'administrative_unit_id' => $unit->public_id,
                ],
            ));

            return $application;
        }, attempts: 3);
    }
}
