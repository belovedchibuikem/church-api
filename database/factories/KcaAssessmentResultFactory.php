<?php

namespace Database\Factories;

use App\Models\KcaAssessmentResult;
use App\Models\KcaEnrollment;
use App\Models\KcaModule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KcaAssessmentResult>
 */
class KcaAssessmentResultFactory extends Factory
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
            'kca_assignment_id' => null,
            'assessment_code' => 'assessment.'.Str::lower(Str::random(10)),
            'result_code' => 'recorded',
            'score' => null,
            'attempt_number' => 1,
            'assessed_by_user_id' => User::factory(),
            'assessed_at' => now(),
        ];
    }
}
