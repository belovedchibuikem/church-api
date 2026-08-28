<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonConsent>
 */
class PersonConsentFactory extends Factory
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
            'purpose' => 'communications.updates',
            'policy_version' => '1.0',
            'source' => 'self_service',
            'granted_at' => now(),
            'withdrawal_source' => null,
            'withdrawn_at' => null,
        ];
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'withdrawal_source' => 'self_service',
            'withdrawn_at' => now(),
        ]);
    }
}
