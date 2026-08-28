<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonPreference>
 */
class PersonPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'locale' => 'en',
            'timezone' => 'UTC',
            'notification_channels' => ['email', 'in_app'],
        ];
    }
}
