<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
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
            'administrative_unit_id' => null,
            'name' => fake()->company(),
            'address_line_one' => fake()->streetAddress(),
            'address_line_two' => null,
            'locality' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'timezone' => fake()->timezone(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}
