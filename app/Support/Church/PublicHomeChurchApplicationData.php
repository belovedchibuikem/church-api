<?php

namespace App\Support\Church;

use App\Church\HomeChurchMeetingSlot;
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

    public readonly ?string $residenceFamilyName;

    public readonly MeetingDay $meetingDay;

    public readonly string $meetingTime;

    public readonly string $contactEmail;

    public readonly string $contactPhone;

    public readonly string $idempotencyKey;

    /** @var list<HomeChurchMeetingSlot> */
    public readonly array $meetingSchedules;

    /**
     * @param  list<array<string, mixed>>|null  $meetingSchedules
     */
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
        MeetingDay $meetingDay,
        string $meetingTime,
        string $contactEmail,
        string $contactPhone,
        string $idempotencyKey,
        ?string $residenceFamilyName = null,
        ?array $meetingSchedules = null,
    ) {
        $this->givenName = Str::squish($givenName);
        $this->middleName = $this->nullableSquished($middleName);
        $this->familyName = Str::squish($familyName);
        $this->preferredName = $this->nullableSquished($preferredName);
        $family = $this->nullableSquished($residenceFamilyName);
        $this->residenceFamilyName = $family;
        $this->proposedName = $family !== null
            ? HomeChurchProposedName::fromResidenceFamily($family)
            : Str::squish($proposedName);
        $this->meetingSchedules = HomeChurchMeetingSchedules::normalize(
            $meetingSchedules,
            $meetingDay,
            DateTimeImmutable::createFromFormat('!H:i', $meetingTime)?->format('H:i') ?? $meetingTime,
        );
        $primary = $this->meetingSchedules[0];
        $this->meetingDay = $primary->day;
        $this->meetingTime = substr($primary->time, 0, 5);
        $this->contactEmail = Str::lower(Str::of($contactEmail)->trim()->toString());
        $this->contactPhone = Str::squish($contactPhone);
        $this->idempotencyKey = trim($idempotencyKey);
    }

    /** @return array<string, int|string|null|list<array{day: string, time: string, activity: string}>> */
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
            'residence_family_name' => $this->residenceFamilyName,
            'expected_participants' => $this->expectedParticipants,
            'meeting_day' => $this->meetingDay->value,
            'meeting_time' => $this->meetingTime,
            'meeting_schedules' => HomeChurchMeetingSchedules::toStorage($this->meetingSchedules),
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
