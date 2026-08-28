<?php

namespace Database\Factories;

use App\Models\KcaEnrollment;
use App\Models\KcaMentorAssignment;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KcaMentorAssignment>
 */
class KcaMentorAssignmentFactory extends Factory
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
            'mentor_person_id' => Person::factory(),
            'assigned_by_user_id' => User::factory(),
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes): array => ['ends_at' => now()->subMinute()]);
    }
}
