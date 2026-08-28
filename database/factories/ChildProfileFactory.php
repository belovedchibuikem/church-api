<?php

namespace Database\Factories;

use App\Models\ChildProfile;
use App\Models\Person;
use App\Safeguarding\MinorStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChildProfile> */
class ChildProfileFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'date_of_birth' => now()->subYears(12)->toDateString(),
            'minor_status' => MinorStatus::ConfirmedMinor,
            'direct_communication_restricted' => true,
            'media_use_restricted' => true,
        ];
    }
}
