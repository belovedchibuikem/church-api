<?php

namespace App\Support\Church;

use App\Exceptions\HomeChurchApplicationIdempotencyConflictException;
use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\HomeChurchApplication;
use App\Models\Location;
use App\Models\Person;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SubmitPublicHomeChurchApplicationAction
{
    public function __construct(
        private readonly CreateHomeChurchApplicationAction $createApplication,
    ) {}

    public function handle(PublicHomeChurchApplicationData $data): PublicHomeChurchApplicationSubmission
    {
        $scopeHash = hash_hmac(
            'sha256',
            "home_church.application.public|{$data->idempotencyKey}",
            (string) config('app.key'),
        );
        $payloadFingerprint = hash('sha256', json_encode($data->fingerprintData(), JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($data, $scopeHash, $payloadFingerprint): PublicHomeChurchApplicationSubmission {
                $existing = HomeChurchApplication::query()
                    ->where('public_idempotency_scope_hash', $scopeHash)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $this->assertMatchingPayload($existing, $payloadFingerprint);

                    return new PublicHomeChurchApplicationSubmission($existing, false);
                }

                $church = Church::query()
                    ->where('public_id', $data->churchPublicId)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now()->utc())
                    ->lockForUpdate()
                    ->firstOrFail();
                $location = Location::query()->where('public_id', $data->locationPublicId)->firstOrFail();
                $administrativeUnit = AdministrativeUnit::query()
                    ->where('public_id', $data->administrativeUnitPublicId)
                    ->firstOrFail();
                $applicant = new Person;
                $applicant->save();
                $applicant->profile()->create([
                    'given_name' => $data->givenName,
                    'middle_name' => $data->middleName,
                    'family_name' => $data->familyName,
                    'preferred_name' => $data->preferredName,
                ]);
                $application = $this->createApplication->handle(new HomeChurchApplicationData(
                    applicant: $applicant,
                    church: $church,
                    location: $location,
                    administrativeUnit: $administrativeUnit,
                    proposedName: $data->proposedName,
                    expectedParticipants: $data->expectedParticipants,
                    meetingDay: $data->meetingDay,
                    meetingTime: $data->meetingTime,
                    contactEmail: $data->contactEmail,
                    contactPhone: $data->contactPhone,
                    guidelinesAgreedAt: now()->utc(),
                ));
                $application->forceFill([
                    'public_idempotency_scope_hash' => $scopeHash,
                    'public_payload_fingerprint' => $payloadFingerprint,
                ])->save();

                return new PublicHomeChurchApplicationSubmission($application, true);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = HomeChurchApplication::query()
                ->where('public_idempotency_scope_hash', $scopeHash)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            $this->assertMatchingPayload($existing, $payloadFingerprint);

            return new PublicHomeChurchApplicationSubmission($existing, false);
        }
    }

    private function assertMatchingPayload(HomeChurchApplication $application, string $payloadFingerprint): void
    {
        if (! hash_equals((string) $application->public_payload_fingerprint, $payloadFingerprint)) {
            throw new HomeChurchApplicationIdempotencyConflictException;
        }
    }
}
