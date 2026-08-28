<?php

namespace Database\Factories;

use App\Mission\MissionSoulJourneyStatus;
use App\Models\Crusade;
use App\Models\MissionSoulJourney;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionSoulJourney>
 */
class MissionSoulJourneyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crusade_id' => Crusade::factory(),
            'person_id' => Person::factory(),
            'connected_church_id' => null,
            'status' => MissionSoulJourneyStatus::New,
            'capture_idempotency_scope_hash' => null,
            'capture_payload_fingerprint' => null,
            'captured_at' => now(),
            'mentor_assigned_at' => null,
            'last_follow_up_at' => null,
            'follow_up_completed_at' => null,
            'follow_up_completion_reason_code' => null,
            'closed_at' => null,
            'closure_reason_code' => null,
        ];
    }

    public function mentorAssigned(): static
    {
        return $this->state(fn (): array => [
            'status' => MissionSoulJourneyStatus::MentorAssigned,
            'mentor_assigned_at' => now(),
        ]);
    }

    public function followUpActive(): static
    {
        return $this->state(fn (): array => [
            'status' => MissionSoulJourneyStatus::FollowUpActive,
            'mentor_assigned_at' => now()->subDay(),
            'last_follow_up_at' => now(),
        ]);
    }

    public function closed(string $reasonCode = 'journey_closed'): static
    {
        return $this->state(fn (): array => [
            'status' => MissionSoulJourneyStatus::Closed,
            'closed_at' => now(),
            'closure_reason_code' => $reasonCode,
        ]);
    }
}
