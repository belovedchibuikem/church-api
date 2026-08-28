<?php

namespace Database\Factories;

use App\Models\PressTranslation;
use App\Models\PressTranslationTransition;
use App\Models\User;
use App\Press\PressTranslationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PressTranslationTransition>
 */
class PressTranslationTransitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'press_translation_id' => PressTranslation::factory(),
            'actor_user_id' => User::factory(),
            'from_status' => PressTranslationStatus::MachineGenerated,
            'to_status' => PressTranslationStatus::UnderReview,
            'reason_code' => 'translation.review.started',
            'occurred_at' => now(),
        ];
    }
}
