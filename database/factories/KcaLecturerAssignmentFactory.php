<?php

namespace Database\Factories;

use App\Models\KcaCohort;
use App\Models\KcaLecturerAssignment;
use App\Models\KcaModule;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KcaLecturerAssignment>
 */
class KcaLecturerAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kca_module_id' => KcaModule::factory(),
            'kca_cohort_id' => KcaCohort::factory(),
            'kca_lesson_id' => null,
            'lecturer_person_id' => Person::factory(),
            'assigned_by_user_id' => User::factory(),
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ];
    }
}
