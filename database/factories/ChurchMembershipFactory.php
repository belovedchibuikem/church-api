<?php

namespace Database\Factories;

use App\Church\ChurchMembershipStatus;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChurchMembership>
 */
class ChurchMembershipFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (ChurchMembership $membership): void {
            $membership->status = ChurchMembershipStatus::Active;
            $membership->active_marker = 1;
            $membership->ended_at = null;
            $membership->end_reason_code = null;
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'church_id' => Church::factory(),
            'home_church_id' => null,
            'joined_at' => now(),
        ];
    }
}
