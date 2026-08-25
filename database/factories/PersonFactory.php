<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }

    public function withProfile(): static
    {
        return $this->has(PersonProfile::factory(), 'profile');
    }
}
