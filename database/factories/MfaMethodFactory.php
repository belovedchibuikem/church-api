<?php

namespace Database\Factories;

use App\Models\MfaMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<MfaMethod>
 */
class MfaMethodFactory extends Factory
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
            'method_type' => 'authenticator_secret',
            'label' => fake()->optional()->words(2, true),
            'secret_hash' => Hash::make('factory-mfa-secret'),
            'verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verified_at' => null,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'revoked_at' => now(),
            'revocation_reason' => 'user_requested',
        ]);
    }
}
