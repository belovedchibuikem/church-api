<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonProfile>
 */
class PersonProfileFactory extends Factory
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
            'given_name' => fake()->firstName(),
            'middle_name' => null,
            'family_name' => fake()->lastName(),
            'preferred_name' => null,
        ];
    }
}
