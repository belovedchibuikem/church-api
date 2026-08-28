<?php

namespace Database\Factories;

use App\Models\FollowUpInteraction;
use App\Models\MentorAssignment;
use App\Models\MissionSoulJourney;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FollowUpInteraction>
 */
class FollowUpInteractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mission_soul_journey_id' => MissionSoulJourney::factory(),
            'mentor_assignment_id' => MentorAssignment::factory(),
            'channel_code' => 'phone',
            'outcome_code' => 'contacted',
            'idempotency_scope_hash' => hash('sha256', Str::uuid()->toString()),
            'payload_fingerprint' => hash('sha256', Str::uuid()->toString()),
            'occurred_at' => now(),
        ];
    }
}
