<?php

namespace Database\Factories;

use App\Models\Crusade;
use App\Models\MissionTeamAssignment;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionTeamAssignment>
 */
class MissionTeamAssignmentFactory extends Factory
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
            'role_code' => 'mentor',
            'assigned_at' => now(),
            'ended_at' => null,
        ];
    }
}
