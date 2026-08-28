<?php

namespace Database\Factories;

use App\Models\PressPublication;
use App\Models\PressPublicationTransition;
use App\Models\User;
use App\Press\PressPublicationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PressPublicationTransition>
 */
class PressPublicationTransitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'press_publication_id' => PressPublication::factory(),
            'actor_user_id' => User::factory(),
            'from_status' => PressPublicationStatus::Manuscript,
            'to_status' => PressPublicationStatus::EditorialReview,
            'reason_code' => 'editorial.review.requested',
            'occurred_at' => now(),
        ];
    }
}
