<?php

namespace Database\Factories;

use App\Church\HomeChurchApplicationStatus;
use App\Church\MeetingDay;
use App\Models\Church;
use App\Models\HomeChurchApplication;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeChurchApplication>
 */
class HomeChurchApplicationFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (HomeChurchApplication $application): void {
            $application->status = HomeChurchApplicationStatus::Draft;
            $application->active_marker = 1;
            $application->status_changed_at = now();
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'applicant_person_id' => Person::factory(),
            'church_id' => Church::factory(),
            'location_id' => fn (array $attributes): int => Church::query()
                ->findOrFail($attributes['church_id'])
                ->location_id,
            'administrative_unit_id' => fn (array $attributes): int => Church::query()
                ->findOrFail($attributes['church_id'])
                ->administrative_unit_id,
            'proposed_name' => fake()->streetName().' Home Church',
            'expected_participants' => fake()->numberBetween(4, 20),
            'meeting_day' => fake()->randomElement(MeetingDay::cases()),
            'meeting_time' => '18:00:00',
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => '+234'.fake()->numerify('##########'),
            'guidelines_agreed_at' => now(),
        ];
    }
}
