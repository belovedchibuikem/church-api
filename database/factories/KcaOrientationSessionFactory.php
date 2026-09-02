<?php

namespace Database\Factories;

use App\Models\KcaCohort;
use App\Models\KcaOrientationSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KcaOrientationSession>
 */
class KcaOrientationSessionFactory extends Factory
{
    protected $model = KcaOrientationSession::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+2 months');

        return [
            'kca_cohort_id' => KcaCohort::factory(),
            'location_id' => null,
            'name' => fake()->sentence(3),
            'venue_label' => fake()->optional()->company(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'capacity' => fake()->optional()->numberBetween(20, 200),
            'notes' => fake()->optional()->paragraph(),
            'published_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
