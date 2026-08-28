<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\FirstTimer;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FirstTimer>
 */
class FirstTimerFactory extends Factory
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
            'church_id' => Church::factory(),
            'home_church_id' => null,
            'registered_at' => now(),
        ];
    }
}
