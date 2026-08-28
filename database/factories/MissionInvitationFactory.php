<?php

namespace Database\Factories;

use App\Mission\MissionInvitationStatus;
use App\Models\MissionInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionInvitation>
 */
class MissionInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crusade_id' => null,
            'requester_person_id' => null,
            'requested_location_id' => null,
            'status' => MissionInvitationStatus::Received,
            'transition_reason_code' => null,
            'status_changed_at' => now(),
        ];
    }
}
