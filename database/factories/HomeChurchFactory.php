<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\HomeChurch;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeChurch>
 */
class HomeChurchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'leader_person_id' => Person::factory(),
            'location_id' => fn (array $attributes): int => Church::query()
                ->findOrFail($attributes['church_id'])
                ->location_id,
            'administrative_unit_id' => fn (array $attributes): int => Church::query()
                ->findOrFail($attributes['church_id'])
                ->administrative_unit_id,
            'name' => fake()->streetName().' Home Church',
        ];
    }
}
