<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PressAuthor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PressAuthor>
 */
class PressAuthorFactory extends Factory
{
    public function definition(): array
    {
        $person = Person::factory();

        return [
            'person_id' => $person,
            'display_name' => fake()->name(),
            'bio' => fake()->paragraph(),
            'status' => 'active',
        ];
    }
}
