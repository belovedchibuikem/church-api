<?php

namespace App\Support\Church;

use App\Church\MeetingDay;
use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\Location;
use App\Models\Person;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class HomeChurchApplicationData
{
    public string $proposedName;

    public string $meetingTime;

    public string $contactEmail;

    public string $contactPhone;

    public function __construct(
        public Person $applicant,
        public Church $church,
        public Location $location,
        public AdministrativeUnit $administrativeUnit,
        string $proposedName,
        public int $expectedParticipants,
        public MeetingDay $meetingDay,
        string $meetingTime,
        string $contactEmail,
        string $contactPhone,
        public CarbonInterface $guidelinesAgreedAt,
    ) {
        $this->proposedName = Str::squish($proposedName);
        $this->meetingTime = $this->normalizeMeetingTime($meetingTime);
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

    private function normalizeMeetingTime(string $value): string
    {
        $normalized = Str::of($value)->trim()->toString();
        $time = DateTimeImmutable::createFromFormat('!H:i', $normalized);

        if ($time === false || $time->format('H:i') !== $normalized) {
            throw new InvalidArgumentException('Meeting time must use 24-hour HH:MM format.');
        }

        return $time->format('H:i:s');
    }
}
