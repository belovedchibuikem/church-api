<?php

namespace App\Support\Church;

use App\Church\HomeChurchMeetingSlot;
use App\Church\MeetingDay;
use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\Location;
use App\Models\Person;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class HomeChurchApplicationData
{
    public string $proposedName;

    public ?string $residenceFamilyName;

    public MeetingDay $meetingDay;

    public string $meetingTime;

    public string $contactEmail;

    public string $contactPhone;

    /** @var list<HomeChurchMeetingSlot> */
    public array $meetingSchedules;

    /**
     * @param  list<array<string, mixed>>|null  $meetingSchedules
     */
    public function __construct(
        public Person $applicant,
        public Church $church,
        public Location $location,
        public AdministrativeUnit $administrativeUnit,
        string $proposedName,
        public int $expectedParticipants,
        MeetingDay $meetingDay,
        string $meetingTime,
        string $contactEmail,
        string $contactPhone,
        public CarbonInterface $guidelinesAgreedAt,
        ?string $residenceFamilyName = null,
        ?array $meetingSchedules = null,
    ) {
        $family = $this->nullableSquished($residenceFamilyName);
        $this->residenceFamilyName = $family;
        $this->proposedName = $family !== null
            ? HomeChurchProposedName::fromResidenceFamily($family)
            : Str::squish($proposedName);
        $this->meetingSchedules = HomeChurchMeetingSchedules::normalize(
            $meetingSchedules,
            $meetingDay,
            $meetingTime,
        );
        $primary = $this->meetingSchedules[0];
        $this->meetingDay = $primary->day;
        $this->meetingTime = $primary->time;
        $this->contactEmail = Str::lower(Str::of($contactEmail)->trim()->toString());
        $this->contactPhone = Str::squish($contactPhone);

        if ($this->proposedName === '' || Str::length($this->proposedName) > 191) {
            throw new InvalidArgumentException('Proposed Home Church names must contain between 1 and 191 characters.');
        }

        if ($this->expectedParticipants < 1 || $this->expectedParticipants > 65535) {
            throw new InvalidArgumentException('Expected participants must be between 1 and 65535.');
        }

        if (
            Str::length($this->contactEmail) > 254
            || filter_var($this->contactEmail, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new InvalidArgumentException('A valid contact email is required.');
        }

        if (
            Str::length($this->contactPhone) > 32
            || ! Str::isMatch('/\A\+?[0-9][0-9 ()-]{4,29}[0-9]\z/', $this->contactPhone)
        ) {
            throw new InvalidArgumentException('A valid contact phone number is required.');
        }

        if ($this->guidelinesAgreedAt->isFuture()) {
            throw new InvalidArgumentException('Guidelines agreement cannot be recorded in the future.');
        }
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
