<?php

namespace Database\Factories;

use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\KcaLessonProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KcaLessonProgress> */
class KcaLessonProgressFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'kca_enrollment_id' => KcaEnrollment::factory(),
            'kca_lesson_id' => KcaLesson::factory(),
            'started_at' => now()->utc(),
            'completed_at' => null,
        ];
    }
}
