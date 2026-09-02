<?php

namespace Database\Factories;

use App\Kca\KcaApplicationState;
use App\Models\KcaApplication;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KcaApplication>
 */
class KcaApplicationFactory extends Factory
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
            'status' => KcaApplicationState::Received,
            'received_at' => now(),
            'reviewed_at' => null,
        ];
    }

    public function interview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => KcaApplicationState::Interview,
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => KcaApplicationState::Reviewed,
            'reviewed_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => KcaApplicationState::Accepted,
            'reviewed_at' => now(),
        ]);
    }

    public function provisionallyAccepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => KcaApplicationState::ProvisionallyAccepted,
            'reviewed_at' => now(),
        ]);
    }
}
