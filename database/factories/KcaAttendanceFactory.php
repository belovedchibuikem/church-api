<?php

namespace Database\Factories;

use App\Kca\KcaAttendanceStatus;
use App\Models\KcaAttendance;
use App\Models\KcaEnrollment;
use App\Models\KcaLesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KcaAttendance>
 */
class KcaAttendanceFactory extends Factory
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
            'kca_lesson_id' => KcaLesson::factory(),
            'status' => KcaAttendanceStatus::Present,
            'session_on' => now()->toDateString(),
            'recorded_by_user_id' => User::factory(),
            'recorded_at' => now(),
        ];
    }
}
