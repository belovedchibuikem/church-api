<?php

namespace Database\Factories;

use App\Kca\KcaAssignmentState;
use App\Models\KcaAssignment;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\KcaModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KcaAssignment>
 */
class KcaAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kca_enrollment_id' => KcaEnrollment::factory(),
            'kca_module_id' => KcaModule::factory(),
            'kca_lesson_id' => function (array $attributes) {
                return KcaLesson::factory()->create([
                    'kca_module_id' => $attributes['kca_module_id'],
                ])->getKey();
            },
            'title' => fake()->sentence(4),
            'state' => KcaAssignmentState::Draft,
            'due_at' => now()->addWeek(),
            'last_transitioned_by_user_id' => null,
        ];
    }

    public function inState(KcaAssignmentState $state): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => $state,
            'assigned_at' => $state === KcaAssignmentState::Draft ? null : now()->subDays(2),
            'submitted_at' => in_array($state, [KcaAssignmentState::Submitted, KcaAssignmentState::MentorReview], true)
                ? now()->subDay()
                : null,
        ]);
    }
}
