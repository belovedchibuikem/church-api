<?php

namespace Database\Factories;

use App\Models\GuardianConsent;
use App\Models\GuardianRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GuardianConsent> */
class GuardianConsentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'guardian_relationship_id' => GuardianRelationship::factory(),
            'purpose' => 'communications.direct',
            'policy_version' => '1.0',
            'source' => 'verified_guardian',
            'granted_at' => now(),
        ];
    }
}
