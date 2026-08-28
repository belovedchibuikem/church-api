<?php

namespace App\Support\Church;

use App\Church\MeetingDay;
use DateTimeImmutable;
use Illuminate\Support\Str;

final class PublicHomeChurchApplicationData
{
    public readonly string $givenName;

    public readonly ?string $middleName;

    public readonly string $familyName;

    public readonly ?string $preferredName;

    public readonly string $proposedName;

    public readonly string $meetingTime;

    public readonly string $contactEmail;

    public readonly string $contactPhone;

    public readonly string $idempotencyKey;

    public function __construct(
        public readonly string $churchPublicId,
        public readonly string $locationPublicId,
        public readonly string $administrativeUnitPublicId,
        string $givenName,
        ?string $middleName,
        string $familyName,
        ?string $preferredName,
        string $proposedName,
        public readonly int $expectedParticipants,
        public readonly MeetingDay $meetingDay,
        string $meetingTime,
        string $contactEmail,
        string $contactPhone,
        string $idempotencyKey,
    ) {
        $this->givenName = Str::squish($givenName);
        $this->middleName = $this->nullableSquished($middleName);
        $this->familyName = Str::squish($familyName);
        $this->preferredName = $this->nullableSquished($preferredName);
        $this->proposedName = Str::squish($proposedName);
        $this->meetingTime = DateTimeImmutable::createFromFormat('!H:i', $meetingTime)?->format('H:i') ?? $meetingTime;
        $this->contactEmail = Str::lower(Str::of($contactEmail)->trim()->toString());
        $this->contactPhone = Str::squish($contactPhone);
        $this->idempotencyKey = trim($idempotencyKey);
    }

    /** @return array<string, int|string|null> */
    public function fingerprintData(): array
    {
        return [
            'church_public_id' => $this->churchPublicId,
            'location_public_id' => $this->locationPublicId,
            'administrative_unit_public_id' => $this->administrativeUnitPublicId,
            'given_name' => $this->givenName,
            'middle_name' => $this->middleName,
            'family_name' => $this->familyName,
            'preferred_name' => $this->preferredName,
            'proposed_name' => $this->proposedName,
            'expected_participants' => $this->expectedParticipants,
            'meeting_day' => $this->meetingDay->value,
            'meeting_time' => $this->meetingTime,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'guidelines_agreed' => 1,
        ];
    }

    private function nullableSquished(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::squish($value);

        return $normalized === '' ? null : $normalized;
    }
}
