<?php

namespace Database\Factories;

use App\Models\EventFeedback;
use App\Models\EventRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventFeedback>
 */
class EventFeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['event_registration_id' => EventRegistration::factory(), 'rating' => 5, 'submitted_at' => now()];
    }
}
