<?php

namespace Database\Factories;

use App\Models\GuardianRelationship;
use App\Models\Person;
use App\Safeguarding\GuardianRelationshipStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GuardianRelationship> */
class GuardianRelationshipFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'guardian_person_id' => Person::factory(),
            'child_person_id' => Person::factory(),
            'relationship_type' => 'parent',
            'status' => GuardianRelationshipStatus::Pending,
        ];
    }
}
