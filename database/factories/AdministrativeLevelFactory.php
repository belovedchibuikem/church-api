<?php

namespace Database\Factories;

use App\Models\AdministrativeLevel;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdministrativeLevel>
 */
class AdministrativeLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'code' => fake()->unique()->bothify('level-####'),
            'name' => fake()->words(2, true),
            'sort_order' => 10,
        ];
    }
}
