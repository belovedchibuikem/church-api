<?php

namespace Database\Factories;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdministrativeUnit>
 */
class AdministrativeUnitFactory extends Factory
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
            'administrative_level_id' => fn (array $attributes): int => AdministrativeLevel::factory()->create([
                'country_id' => $attributes['country_id'],
            ])->getKey(),
            'parent_id' => null,
            'name' => fake()->city(),
            'reference_code' => fake()->unique()->bothify('UNIT-####'),
        ];
    }
}
