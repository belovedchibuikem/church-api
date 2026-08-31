<?php

namespace Database\Factories;

use App\Models\Crusade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Crusade>
 */
class CrusadeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'location_id' => null,
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addDays(2),
            'published_at' => null,
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['published_at' => now()->subMinute()]);
    }
}
