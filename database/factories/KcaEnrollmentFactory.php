<?php

namespace Database\Factories;

use App\Models\KcaApplication;
use App\Models\KcaCohort;
use App\Models\KcaEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KcaEnrollment>
 */
class KcaEnrollmentFactory extends Factory
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
            'person_id' => fn (array $attributes): int => KcaApplication::query()
                ->findOrFail($attributes['kca_application_id'])
                ->person_id,
            'kca_cohort_id' => KcaCohort::factory(),
            'kca_year_id' => fn (array $attributes): int => KcaCohort::query()
                ->findOrFail($attributes['kca_cohort_id'])
                ->kca_year_id,
            'registration_number' => 'KCA-'.Str::upper(Str::random(12)),
            'starts_on' => now()->toDateString(),
            'created_by_user_id' => User::factory(),
        ];
    }
}
