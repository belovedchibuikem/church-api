<?php

namespace Database\Factories;

use App\Models\MinistryEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MinistryEvent>
 */
class MinistryEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => null, 'category_code' => 'training', 'name' => fake()->sentence(3),
            'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addDay(),
            'registration_opens_at' => now()->subDay(), 'registration_closes_at' => now()->addWeeks(3),
            'fee_amount_minor' => null, 'fee_currency' => null, 'capacity' => null, 'published_at' => null,
        ];
    }

    public function published(?CarbonInterface $at = null): static
    {
        return $this->state(fn (): array => [
            'published_at' => $at ?? now()->subMinute(),
        ]);
    }
}
