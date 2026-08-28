<?php

namespace Database\Factories;

use App\Kca\KcaApplicationState;
use App\Models\KcaAdmissionDecision;
use App\Models\KcaApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KcaAdmissionDecision>
 */
class KcaAdmissionDecisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kca_application_id' => KcaApplication::factory()->accepted(),
            'outcome' => KcaApplicationState::Accepted,
            'reason_code' => null,
            'decided_by_user_id' => User::factory(),
            'decided_at' => now(),
        ];
    }
}
