<?php

namespace Database\Factories;

use App\Models\AdministrativeUnit;
use App\Models\Church;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Church>
 */
class ChurchFactory extends Factory
{
    public function published(): static
    {
        return $this->state(fn (): array => ['published_at' => now()->utc()]);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'administrative_unit_id' => AdministrativeUnit::factory(),
            'location_id' => function (array $attributes): int {
                $unit = AdministrativeUnit::query()->findOrFail($attributes['administrative_unit_id']);

                return Location::factory()->create([
                    'country_id' => $unit->country_id,
                    'administrative_unit_id' => $unit->getKey(),
                ])->getKey();
            },
            'name' => fake()->company().' Church',
        ];
    }
}
