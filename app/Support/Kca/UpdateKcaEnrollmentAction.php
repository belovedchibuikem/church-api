<?php

namespace App\Support\Kca;

use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\KcaYear;
use App\Models\User;
use App\Support\Audit\AuditEventData;
use App\Support\Audit\RecordAuditEventAction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateKcaEnrollmentAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(KcaEnrollment $enrollment, array $attributes, User $actor): KcaEnrollment
    {
        return DB::transaction(function () use ($enrollment, $attributes, $actor): KcaEnrollment {
            $locked = KcaEnrollment::query()->lockForUpdate()->findOrFail($enrollment->getKey());

            if (isset($attributes['cohort']) && $attributes['cohort'] instanceof KcaCohort) {
                /** @var KcaCohort $cohort */
                $cohort = $attributes['cohort'];
                $year = KcaYear::query()->findOrFail($cohort->kca_year_id);
                $locked->kca_cohort_id = $cohort->getKey();
                $locked->kca_year_id = $year->getKey();
            }

            if (array_key_exists('registration_number', $attributes) && is_string($attributes['registration_number'])) {
                $registrationNumber = trim($attributes['registration_number']);
                if ($registrationNumber === '' || Str::length($registrationNumber) > 100) {
                    throw new InvalidArgumentException('KCA registration numbers must contain 1 to 100 characters.');
                }
                $locked->registration_number = $registrationNumber;
            }

            if (isset($attributes['starts_on']) && $attributes['starts_on'] instanceof CarbonInterface) {
                $locked->starts_on = $attributes['starts_on']->toImmutable()->startOfDay();
            }

            $locked->save();

            $this->syncPerson($locked, $attributes);
            $this->syncApplicationData($locked, $attributes);

            $this->recordAuditEvent->handle(new AuditEventData(
                action: 'kca.enrollment.updated',
                actor: $actor,
                targetType: 'kca_enrollment',
                targetId: $locked->public_id,
            ));

            return $locked->fresh([
                'person.profile',
                'person.user:id,person_id,email,name',
                'application',
                'year:id,public_id,name',
                'cohort:id,public_id,name',
            ]) ?? $locked;
        }, attempts: 3);
    }

    /** @param  array<string, mixed>  $attributes */
    private function syncPerson(KcaEnrollment $enrollment, array $attributes): void
    {
        $person = $enrollment->person;
        if ($person === null) {
            return;
        }

        $profile = $person->profile;
        if ($profile !== null) {
            $profileUpdates = [];
            foreach (['given_name', 'family_name', 'phone'] as $field) {
                if (array_key_exists($field, $attributes) && is_string($attributes[$field]) && trim($attributes[$field]) !== '') {
                    $profileUpdates[$field] = trim($attributes[$field]);
                }
            }
            if ($profileUpdates !== []) {
                $profile->forceFill($profileUpdates)->save();
            }
        }

        if (array_key_exists('email', $attributes) && is_string($attributes['email']) && trim($attributes['email']) !== '') {
            $user = $person->user;
            if ($user !== null) {
                $user->forceFill(['email' => trim($attributes['email'])])->save();
            }
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function syncApplicationData(KcaEnrollment $enrollment, array $attributes): void
    {
        $application = $enrollment->application;
        if ($application === null || ! isset($attributes['application_data']) || ! is_array($attributes['application_data'])) {
            return;
        }

        $current = is_array($application->application_data) ? $application->application_data : [];
        $application->application_data = array_merge($current, $attributes['application_data']);
        $application->save();
    }
}
