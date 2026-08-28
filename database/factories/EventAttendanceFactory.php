<?php

namespace Database\Factories;

use App\Models\EventAttendance;
use App\Models\EventRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAttendance>
 */
class EventAttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['event_registration_id' => EventRegistration::factory(), 'source_code' => 'manual', 'attended_at' => now()];
    }
}
