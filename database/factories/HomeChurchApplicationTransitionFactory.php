<?php

namespace Database\Factories;

use App\Church\HomeChurchApplicationStatus;
use App\Models\HomeChurchApplication;
use App\Models\HomeChurchApplicationTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeChurchApplicationTransition>
 */
class HomeChurchApplicationTransitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'home_church_application_id' => HomeChurchApplication::factory(),
            'actor_user_id' => User::factory(),
            'from_status' => HomeChurchApplicationStatus::Draft,
            'to_status' => HomeChurchApplicationStatus::Submitted,
            'reason_code' => 'application_submitted',
            'correlation_id' => fake()->uuid(),
            'occurred_at' => now(),
        ];
    }
}
