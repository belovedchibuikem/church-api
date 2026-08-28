<?php

namespace Database\Factories;

use App\Models\MentorAssignment;
use App\Models\MissionSoulJourney;
use App\Models\MissionTeamAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MentorAssignment>
 */
class MentorAssignmentFactory extends Factory
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
            'mission_team_assignment_id' => MissionTeamAssignment::factory(),
            'idempotency_scope_hash' => null,
            'payload_fingerprint' => null,
            'assigned_at' => now(),
            'ended_at' => null,
        ];
    }
}
