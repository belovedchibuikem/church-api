<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'identifier_hash' => hash('sha256', Str::uuid()->toString()),
            'label' => fake()->optional()->words(2, true),
            'device_type' => fake()->randomElement(['phone', 'tablet', 'browser']),
            'platform' => fake()->randomElement(['Android', 'iOS', 'Windows', 'macOS']),
            'app_version' => fake()->numerify('#.##.#'),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'revoked_at' => now(),
            'revocation_reason' => 'user_requested',
        ]);
    }
}
